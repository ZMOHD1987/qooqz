<?php
declare(strict_types=1);

/**
 * PdoHomepageSectionsRepository
 *
 * Implements HomepageSectionsRepositoryInterface.
 * Supports all languages via homepage_section_translations + languages tables.
 * New fields: component, layout_config
 *
 * Location: api/v1/models/homepage_sections/repositories/PdoHomepageSectionsRepository.php
 */
final class PdoHomepageSectionsRepository implements HomepageSectionsRepositoryInterface
{
    private PDO $pdo;

    // All columns in homepage_sections (including new fields)
    private const SECTION_COLUMNS = [
        'id', 'tenant_id', 'section_type', 'component', 'title', 'subtitle',
        'layout_type', 'layout_config', 'items_per_row', 'background_color',
        'text_color', 'padding', 'custom_css', 'custom_html', 'data_source',
        'is_active', 'sort_order', 'theme_id', 'created_at', 'updated_at',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================================
    // Interface Implementation
    // =========================================================================

    public function all(
        int $tenantId,
        ?string $sectionType = null,
        ?int $themeId = null,
        string $lang = 'en'
    ): array {
        $sql = "
            SELECT
                hs.id, hs.tenant_id, hs.section_type, hs.component,
                hs.layout_type, hs.layout_config, hs.items_per_row,
                hs.background_color, hs.text_color, hs.padding,
                hs.custom_css, hs.custom_html, hs.data_source,
                hs.is_active, hs.sort_order, hs.theme_id,
                hs.created_at, hs.updated_at,
                COALESCE(hst.title,    hs.title)    AS title,
                COALESCE(hst.subtitle, hs.subtitle) AS subtitle,
                :lang AS resolved_lang
            FROM homepage_sections hs
            LEFT JOIN homepage_section_translations hst
                   ON hs.id = hst.section_id AND hst.language_code = :lang2
            WHERE hs.tenant_id = :tenantId
        ";

        $params = [
            ':tenantId' => $tenantId,
            ':lang'     => $lang,
            ':lang2'    => $lang,
        ];

        if ($sectionType !== null) {
            $sql .= " AND hs.section_type = :sectionType";
            $params[':sectionType'] = $sectionType;
        }

        if ($themeId !== null) {
            $sql .= " AND hs.theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $sql .= " ORDER BY hs.sort_order ASC, hs.id ASC";

        return $this->fetchAll($sql, $params);
    }

    public function find(
        int $tenantId,
        int $id,
        string $lang = 'en',
        bool $allTranslations = false
    ): ?array {
        if ($allTranslations) {
            $row = $this->findById($tenantId, $id);
            if ($row) {
                $row['translations']  = $this->getTranslations($id);
                $row['resolved_lang'] = $lang;
            }
            return $row;
        }

        $sql = "
            SELECT
                hs.id, hs.tenant_id, hs.section_type, hs.component,
                hs.layout_type, hs.layout_config, hs.items_per_row,
                hs.background_color, hs.text_color, hs.padding,
                hs.custom_css, hs.custom_html, hs.data_source,
                hs.is_active, hs.sort_order, hs.theme_id,
                hs.created_at, hs.updated_at,
                COALESCE(hst.title,    hs.title)    AS title,
                COALESCE(hst.subtitle, hs.subtitle) AS subtitle,
                :lang AS resolved_lang
            FROM homepage_sections hs
            LEFT JOIN homepage_section_translations hst
                   ON hs.id = hst.section_id AND hst.language_code = :lang2
            WHERE hs.tenant_id = :tenantId AND hs.id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tenantId' => $tenantId,
            ':id'       => $id,
            ':lang'     => $lang,
            ':lang2'    => $lang,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM homepage_sections
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
        $oldData  = $isUpdate ? $this->findById($tenantId, (int) $data['id']) : null;

        $this->pdo->beginTransaction();
        try {
            if ($isUpdate) {
                $id = $this->update($tenantId, $data);
            } else {
                $id = $this->insert($tenantId, $data);
            }

            if (!empty($data['translations']) && is_array($data['translations'])) {
                $this->saveTranslations($id, $data['translations']);
            }

            if ($userId !== null) {
                $this->logAction(
                    $tenantId,
                    $userId,
                    $isUpdate ? 'update' : 'create',
                    $id,
                    $oldData,
                    $data
                );
            }

            $this->pdo->commit();
            return $id;

        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $tenantId, int $id, ?int $userId = null): bool
    {
        $oldData = $this->findById($tenantId, $id);
        if (!$oldData) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                "DELETE FROM homepage_section_translations WHERE section_id = :id"
            )->execute([':id' => $id]);

            $stmt = $this->pdo->prepare(
                "DELETE FROM homepage_sections WHERE tenant_id = :tenantId AND id = :id"
            );
            $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);

            if ($userId !== null) {
                $this->logAction($tenantId, $userId, 'delete', $id, $oldData, null);
            }

            $this->pdo->commit();
            return true;

        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
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

    public function getActiveSections(
        int $tenantId,
        string $lang = 'en',
        ?int $themeId = null
    ): array {
        $sql = "
            SELECT
                hs.id, hs.section_type, hs.component,
                hs.layout_type, hs.layout_config, hs.items_per_row,
                hs.background_color, hs.text_color, hs.padding,
                hs.custom_css, hs.custom_html, hs.data_source,
                hs.sort_order, hs.theme_id,
                COALESCE(hst.title,    hs.title)    AS title,
                COALESCE(hst.subtitle, hs.subtitle) AS subtitle,
                :lang AS resolved_lang
            FROM homepage_sections hs
            LEFT JOIN homepage_section_translations hst
                   ON hs.id = hst.section_id AND hst.language_code = :lang2
            WHERE hs.tenant_id = :tenantId AND hs.is_active = 1
        ";

        $params = [
            ':tenantId' => $tenantId,
            ':lang'     => $lang,
            ':lang2'    => $lang,
        ];

        if ($themeId !== null) {
            $sql .= " AND hs.theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $sql .= " ORDER BY hs.sort_order ASC, hs.id ASC";

        return $this->fetchAll($sql, $params);
    }

    public function saveTranslations(int $sectionId, array $translations): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO homepage_section_translations (section_id, language_code, title, subtitle)
            VALUES (:section_id, :lang, :title, :subtitle)
            ON DUPLICATE KEY UPDATE
                title    = VALUES(title),
                subtitle = VALUES(subtitle)
        ");

        foreach ($translations as $langCode => $trans) {
            // تجاهل اللغات الفارغة تماماً
            if (empty($trans['title']) && empty($trans['subtitle'])) {
                continue;
            }
            $stmt->execute([
                ':section_id' => $sectionId,
                ':lang'       => $langCode,
                ':title'      => $trans['title']    ?? null,
                ':subtitle'   => $trans['subtitle'] ?? null,
            ]);
        }
    }

    public function getTranslations(int $sectionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT hst.language_code, hst.title, hst.subtitle,
                   l.name AS language_name, l.direction
            FROM homepage_section_translations hst
            LEFT JOIN languages l ON l.code = hst.language_code
            WHERE hst.section_id = :section_id
            ORDER BY hst.language_code ASC
        ");
        $stmt->execute([':section_id' => $sectionId]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['language_code']] = [
                'title'         => $row['title'],
                'subtitle'      => $row['subtitle'],
                'language_name' => $row['language_name'],
                'direction'     => $row['direction'] ?? 'ltr',
            ];
        }
        return $result;
    }

    public function resolveUserLang(int $userId, string $default = 'en'): string
    {
        $stmt = $this->pdo->prepare("
            SELECT preferred_language FROM users
            WHERE id = :id AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':id' => $userId]);
        $lang = $stmt->fetchColumn();
        return ($lang && $lang !== '') ? (string) $lang : $default;
    }

    public function getSupportedLanguages(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT code, name, direction FROM languages ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function insert(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO homepage_sections (
                tenant_id, section_type, component, title, subtitle,
                layout_type, layout_config, items_per_row,
                background_color, text_color, padding,
                custom_css, custom_html, data_source,
                is_active, sort_order, theme_id, created_at
            ) VALUES (
                :tenantId, :section_type, :component, :title, :subtitle,
                :layout_type, :layout_config, :items_per_row,
                :background_color, :text_color, :padding,
                :custom_css, :custom_html, :data_source,
                :is_active, :sort_order, :theme_id, NOW()
            )
        ");

        $stmt->execute($this->buildParams($tenantId, $data));
        return (int) $this->pdo->lastInsertId();
    }

    private function update(int $tenantId, array $data): int
    {
        $id   = (int) $data['id'];
        $stmt = $this->pdo->prepare("
            UPDATE homepage_sections SET
                section_type     = :section_type,
                component        = :component,
                title            = :title,
                subtitle         = :subtitle,
                layout_type      = :layout_type,
                layout_config    = :layout_config,
                items_per_row    = :items_per_row,
                background_color = :background_color,
                text_color       = :text_color,
                padding          = :padding,
                custom_css       = :custom_css,
                custom_html      = :custom_html,
                data_source      = :data_source,
                is_active        = :is_active,
                sort_order       = :sort_order,
                theme_id         = :theme_id,
                updated_at       = NOW()
            WHERE tenant_id = :tenantId AND id = :id
        ");

        $params       = $this->buildParams($tenantId, $data);
        $params[':id'] = $id;
        $stmt->execute($params);
        return $id;
    }

    /**
     * Build shared param array for INSERT and UPDATE.
     */
    private function buildParams(int $tenantId, array $d): array
    {
        return [
            ':tenantId'        => $tenantId,
            ':section_type'    => $d['section_type'],
            ':component'       => $d['component']        ?? null,
            ':title'           => $d['title']            ?? null,
            ':subtitle'        => $d['subtitle']         ?? null,
            ':layout_type'     => $d['layout_type']      ?? 'grid',
            ':layout_config'   => isset($d['layout_config'])
                                    ? (is_array($d['layout_config'])
                                        ? json_encode($d['layout_config'], JSON_UNESCAPED_UNICODE)
                                        : $d['layout_config'])
                                    : null,
            ':items_per_row'   => (int) ($d['items_per_row']   ?? 4),
            ':background_color' => $d['background_color'] ?? '#FFFFFF',
            ':text_color'      => $d['text_color']        ?? '#000000',
            ':padding'         => $d['padding']           ?? '40px 0',
            ':custom_css'      => $d['custom_css']        ?? null,
            ':custom_html'     => $d['custom_html']       ?? null,
            ':data_source'     => $d['data_source']       ?? null,
            ':is_active'       => (int) ($d['is_active']  ?? 1),
            ':sort_order'      => (int) ($d['sort_order'] ?? 0),
            ':theme_id'        => !empty($d['theme_id']) ? (int) $d['theme_id'] : null,
        ];
    }

    private function fetchAll(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function logAction(
        int $tenantId,
        int $userId,
        string $action,
        int $entityId,
        ?array $oldData,
        ?array $newData
    ): void {
        $changes = match ($action) {
            'update' => $oldData && $newData
                ? json_encode(['old' => $oldData, 'new' => $newData], JSON_UNESCAPED_UNICODE)
                : null,
            'delete' => $oldData
                ? json_encode(['deleted' => $oldData], JSON_UNESCAPED_UNICODE)
                : null,
            'create' => $newData
                ? json_encode(['created' => $newData], JSON_UNESCAPED_UNICODE)
                : null,
            default  => null,
        };

        $this->pdo->prepare("
            INSERT INTO entity_logs
                (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
            VALUES
                (:tenantId, :userId, 'homepage_section', :entityId, :action, :changes, :ip, NOW())
        ")->execute([
            ':tenantId' => $tenantId,
            ':userId'   => $userId,
            ':entityId' => $entityId,
            ':action'   => $action,
            ':changes'  => $changes,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
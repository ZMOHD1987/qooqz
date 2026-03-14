<?php
declare(strict_types=1);

// api/v1/models/banners/repositories/PdoBannersRepository.php
// Updated: banners table no longer has image_url / mobile_image_url.
// Images are stored in the unified images table with image_type_id = 9.

final class PdoBannersRepository
{
    private PDO $pdo;
    private const IMAGE_TYPE_ID = 9; // banner image type

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─────────────────────────────────────────────────────
    // LIST
    // ─────────────────────────────────────────────────────
    public function all(
        int $tenantId,
        ?string $position = null,
        ?int $themeId = null,
        ?int $isActive = null,
        string $lang = 'en'
    ): array {
        $sql = "
            SELECT b.id, b.tenant_id, b.entity_id, b.theme_id, b.link_url,
                   b.position, b.background_color, b.text_color, b.button_style,
                   b.sort_order, b.is_active, b.start_date, b.end_date,
                   b.created_at, b.updated_at,
                   COALESCE(bt.title,    b.title)    AS title,
                   COALESCE(bt.subtitle, b.subtitle) AS subtitle,
                   COALESCE(bt.link_text, b.link_text) AS link_text
            FROM banners b
            LEFT JOIN banner_translations bt
                ON b.id = bt.banner_id AND bt.language_code = :lang
            WHERE b.tenant_id = :tenantId
        ";

        $params = [':tenantId' => $tenantId, ':lang' => $lang];

        if ($position !== null) {
            $sql .= " AND b.position = :position";
            $params[':position'] = $position;
        }
        if ($themeId !== null) {
            $sql .= " AND b.theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }
        if ($isActive !== null) {
            $sql .= " AND b.is_active = :isActive";
            $params[':isActive'] = $isActive;
        }

        $sql .= " ORDER BY b.sort_order ASC, b.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────
    // FIND
    // ─────────────────────────────────────────────────────
    public function find(int $tenantId, int $id, string $lang = 'en', bool $allTranslations = false): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*,
                   COALESCE(bt.title,    b.title)    AS title,
                   COALESCE(bt.subtitle, b.subtitle) AS subtitle,
                   COALESCE(bt.link_text, b.link_text) AS link_text
            FROM banners b
            LEFT JOIN banner_translations bt
                ON b.id = bt.banner_id AND bt.language_code = :lang
            WHERE b.tenant_id = :tenantId AND b.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenantId' => $tenantId, ':lang' => $lang, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        if ($allTranslations) {
            $row['translations'] = $this->getTranslations($id);
        }
        return $row;
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM banners
            WHERE tenant_id = :tenantId AND id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ─────────────────────────────────────────────────────
    // SAVE (CREATE / UPDATE)
    // ─────────────────────────────────────────────────────
    public function save(int $tenantId, array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData  = $isUpdate ? $this->findById($tenantId, (int)$data['id']) : null;

        // Shared field values
        $fields = [
            ':title'            => $data['title'] ?? '',
            ':subtitle'         => $data['subtitle']         ?? null,
            ':link_url'         => $data['link_url']         ?? null,
            ':link_text'        => $data['link_text']        ?? null,
            ':position'         => $data['position']         ?? 'homepage_main',
            ':theme_id'         => $data['theme_id']         ?? null,
            ':background_color' => $data['background_color'] ?? '#FFFFFF',
            ':text_color'       => $data['text_color']       ?? '#000000',
            ':button_style'     => $data['button_style']     ?? null,
            ':sort_order'       => (int)($data['sort_order'] ?? 0),
            ':is_active'        => (int)($data['is_active']  ?? 1),
            ':start_date'       => $data['start_date']       ?? null,
            ':end_date'         => $data['end_date']         ?? null,
        ];

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE banners
                SET title            = :title,
                    subtitle         = :subtitle,
                    link_url         = :link_url,
                    link_text        = :link_text,
                    position         = :position,
                    theme_id         = :theme_id,
                    background_color = :background_color,
                    text_color       = :text_color,
                    button_style     = :button_style,
                    sort_order       = :sort_order,
                    is_active        = :is_active,
                    start_date       = :start_date,
                    end_date         = :end_date,
                    updated_at       = NOW()
                WHERE tenant_id = :tenantId AND id = :id
            ");
            $stmt->execute(array_merge($fields, [':tenantId' => $tenantId, ':id' => (int)$data['id']]));
            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO banners
                    (tenant_id, title, subtitle, link_url, link_text, position,
                     theme_id, background_color, text_color, button_style,
                     sort_order, is_active, start_date, end_date, created_at)
                VALUES
                    (:tenantId, :title, :subtitle, :link_url, :link_text, :position,
                     :theme_id, :background_color, :text_color, :button_style,
                     :sort_order, :is_active, :start_date, :end_date, NOW())
            ");
            $stmt->execute(array_merge($fields, [':tenantId' => $tenantId]));
            $id = (int)$this->pdo->lastInsertId();
        }

        // Save translations
        if (!empty($data['translations']) && is_array($data['translations'])) {
            $this->saveTranslations($id, $data['translations']);
        }

        // Update image owner association if image_id supplied
        if (!empty($data['image_id'])) {
            $this->attachImage($id, (int)$data['image_id'], $tenantId);
        }

        // Log
        if ($userId) {
            $this->logAction($tenantId, $userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }

    // ─────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────
    public function delete(int $tenantId, int $id, ?int $userId = null): bool
    {
        $oldData = $this->findById($tenantId, $id);

        $this->pdo->beginTransaction();
        try {
            // Delete translations
            $this->pdo->prepare("DELETE FROM banner_translations WHERE banner_id = :id")
                ->execute([':id' => $id]);

            // Delete banner
            $stmt = $this->pdo->prepare("DELETE FROM banners WHERE tenant_id = :tenantId AND id = :id");
            $ok   = $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);

            if ($userId && $oldData) {
                $this->logAction($tenantId, $userId, 'delete', $id, $oldData, null);
            }

            $this->pdo->commit();
            return (bool)$ok;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────
    // TRANSLATIONS
    // ─────────────────────────────────────────────────────
    public function saveTranslations(int $bannerId, array $translations): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO banner_translations (banner_id, language_code, title, subtitle, link_text)
            VALUES (:banner_id, :lang, :title, :subtitle, :link_text)
            ON DUPLICATE KEY UPDATE
                title     = VALUES(title),
                subtitle  = VALUES(subtitle),
                link_text = VALUES(link_text)
        ");

        foreach ($translations as $lang => $t) {
            $stmt->execute([
                ':banner_id' => $bannerId,
                ':lang'      => $lang,
                ':title'     => $t['title']     ?? null,
                ':subtitle'  => $t['subtitle']  ?? null,
                ':link_text' => $t['link_text'] ?? null,
            ]);
        }
    }

    public function getTranslations(int $bannerId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT language_code, title, subtitle, link_text
            FROM banner_translations
            WHERE banner_id = :id
        ");
        $stmt->execute([':id' => $bannerId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[$row['language_code']] = [
                'title'     => $row['title'],
                'subtitle'  => $row['subtitle'],
                'link_text' => $row['link_text'],
            ];
        }
        return $out;
    }

    // ─────────────────────────────────────────────────────
    // IMAGES (unified table)
    // ─────────────────────────────────────────────────────

    /**
     * Attach an existing image record to this banner (update owner_id).
     */
    private function attachImage(int $bannerId, int $imageId, int $tenantId): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE images
                SET owner_id = :owner_id
                WHERE id = :id AND tenant_id = :tenantId AND image_type_id = :typeId
            ");
            $stmt->execute([
                ':owner_id' => $bannerId,
                ':id'       => $imageId,
                ':tenantId' => $tenantId,
                ':typeId'   => self::IMAGE_TYPE_ID,
            ]);
        } catch (Throwable $e) {
            error_log('[PdoBannersRepository] attachImage failed: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────
    // ACTIVE BANNERS (public/frontend use)
    // ─────────────────────────────────────────────────────
    public function getActiveBanners(int $tenantId, string $position, string $lang = 'en', ?int $themeId = null): array
    {
        $sql = "
            SELECT b.id, b.link_url, b.background_color, b.text_color, b.button_style, b.sort_order,
                   COALESCE(bt.title,    b.title)     AS title,
                   COALESCE(bt.subtitle, b.subtitle)  AS subtitle,
                   COALESCE(bt.link_text, b.link_text) AS link_text,
                   img.url AS image_url, img.thumb_url
            FROM banners b
            LEFT JOIN banner_translations bt
                ON b.id = bt.banner_id AND bt.language_code = :lang
            LEFT JOIN images img
                ON img.owner_id = b.id AND img.image_type_id = :typeId AND img.is_main = 1
            WHERE b.tenant_id = :tenantId
              AND b.position  = :position
              AND b.is_active = 1
              AND (b.start_date IS NULL OR b.start_date <= NOW())
              AND (b.end_date   IS NULL OR b.end_date   >= NOW())
        ";

        $params = [
            ':tenantId' => $tenantId,
            ':position' => $position,
            ':lang'     => $lang,
            ':typeId'   => self::IMAGE_TYPE_ID,
        ];

        if ($themeId !== null) {
            $sql .= " AND b.theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $sql .= " ORDER BY b.sort_order ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPositions(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT position FROM banners
            WHERE tenant_id = :tenantId ORDER BY position ASC
        ");
        $stmt->execute([':tenantId' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // ─────────────────────────────────────────────────────
    // AUDIT LOG
    // ─────────────────────────────────────────────────────
    private function logAction(int $tenantId, int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
    {
        try {
            $changes = null;
            if ($action === 'update' && $oldData && $newData) {
                $changes = json_encode(['old' => $oldData, 'new' => $newData]);
            } elseif ($action === 'delete' && $oldData) {
                $changes = json_encode(['deleted' => $oldData]);
            } elseif ($action === 'create' && $newData) {
                $changes = json_encode(['created' => $newData]);
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
                VALUES (:tenantId, :userId, 'banner', :entityId, :action, :changes, :ip, NOW())
            ");
            $stmt->execute([
                ':tenantId' => $tenantId,
                ':userId'   => $userId,
                ':entityId' => $entityId,
                ':action'   => $action,
                ':changes'  => $changes,
                ':ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('[PdoBannersRepository] logAction failed: ' . $e->getMessage());
        }
    }
}
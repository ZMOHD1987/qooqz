<?php
declare(strict_types=1);

/**
 * SeoAutoManager - Auto-populate seo_meta + seo_meta_translations
 *
 * Usage (in any route after entity creation/update):
 *   require_once __DIR__ . '/../shared/helpers/SeoAutoManager.php';
 *   SeoAutoManager::sync($pdo, 'entity', $entityId, [
 *       'name'        => 'Store Name',
 *       'slug'        => 'store-slug',
 *       'description' => 'Description text',
 *       'tenant_id'   => 1
 *   ]);
 *
 *   SeoAutoManager::syncTranslation($pdo, 'entity', $entityId, 'ar', [
 *       'name'        => 'اسم المتجر',
 *       'description' => 'وصف المتجر'
 *   ]);
 */
class SeoAutoManager
{
    /**
     * Sync seo_meta row for an entity.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for upsert.
     *
     * @param PDO    $pdo
     * @param string $entityType  e.g. entity, product, category, page
     * @param int    $entityId
     * @param array  $data        Keys: name, slug, description, tenant_id
     */
    public static function sync(PDO $pdo, string $entityType, int $entityId, array $data): void
    {
        $slug        = $data['slug'] ?? '';
        $tenantId    = isset($data['tenant_id']) ? (int)$data['tenant_id'] : null;
        $canonical   = $slug ? '/' . $entityType . '/' . $slug : null;
        $robots      = 'index, follow';

        // Build simple JSON-LD schema
        $name        = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $schema      = json_encode([
            '@context' => 'https://schema.org',
            '@type'    => self::schemaType($entityType),
            'name'     => $name,
            'description' => mb_substr($description, 0, 300),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $sql = "INSERT INTO seo_meta
                    (tenant_id, entity_type, entity_id, canonical_url, robots, schema_markup)
                VALUES
                    (:tenant_id, :entity_type, :entity_id, :canonical_url, :robots, :schema_markup)
                ON DUPLICATE KEY UPDATE
                    canonical_url  = VALUES(canonical_url),
                    robots         = VALUES(robots),
                    schema_markup  = VALUES(schema_markup),
                    updated_at     = NOW()";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tenant_id'      => $tenantId,
            ':entity_type'    => $entityType,
            ':entity_id'      => $entityId,
            ':canonical_url'  => $canonical,
            ':robots'         => $robots,
            ':schema_markup'  => $schema,
        ]);

        // If language_code is provided, auto-create translation too
        $langCode = $data['language_code'] ?? null;
        if ($langCode) {
            self::syncTranslation($pdo, $entityType, $entityId, $langCode, $data);
        }
    }

    /**
     * Sync SEO translations for ALL existing translations of an entity.
     * Queries the appropriate translations table and syncs each language.
     *
     * @param PDO    $pdo
     * @param string $entityType  e.g. entity, product, category
     * @param int    $entityId
     */
    public static function syncAllTranslations(PDO $pdo, string $entityType, int $entityId): void
    {
        $seoMetaId = self::getSeoMetaId($pdo, $entityType, $entityId);
        if (!$seoMetaId) {
            return;
        }

        $tableMap = [
            'entity'   => ['table' => 'entity_translations',  'fk' => 'entity_id',  'name' => 'store_name'],
            'product'  => ['table' => 'product_translations',  'fk' => 'product_id', 'name' => 'name'],
            'category' => ['table' => 'category_translations', 'fk' => 'category_id','name' => 'name'],
        ];

        $config = $tableMap[$entityType] ?? null;
        if (!$config) {
            return;
        }

        // Whitelist validation - table/column names come from internal array only
        $allowedTables = ['entity_translations', 'product_translations', 'category_translations'];
        if (!in_array($config['table'], $allowedTables, true)) {
            return;
        }

        $stmt = $pdo->prepare(
            "SELECT language_code, {$config['name']} AS name, description
             FROM {$config['table']}
             WHERE {$config['fk']} = ?"
        );
        $stmt->execute([$entityId]);
        $translations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($translations as $tr) {
            self::syncTranslation($pdo, $entityType, $entityId, $tr['language_code'], [
                'name'        => $tr['name'] ?? '',
                'description' => $tr['description'] ?? '',
            ]);
        }
    }

    /**
     * Sync a specific language translation for seo_meta.
     * Called when entity translations are saved.
     *
     * @param PDO    $pdo
     * @param string $entityType
     * @param int    $entityId
     * @param string $langCode
     * @param array  $data  Keys: name, description
     */
    public static function syncTranslation(
        PDO $pdo,
        string $entityType,
        int $entityId,
        string $langCode,
        array $data
    ): void {
        $seoMetaId = self::getSeoMetaId($pdo, $entityType, $entityId);
        if (!$seoMetaId) {
            return;
        }

        $name        = $data['name'] ?? '';
        $description = $data['description'] ?? '';

        $metaTitle       = $name ? mb_substr($name, 0, 255) : null;
        $metaDescription = $description ? mb_substr($description, 0, 160) : null;
        $metaKeywords    = self::generateKeywords($name, $description);

        self::upsertTranslation($pdo, $seoMetaId, $langCode, [
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords'    => $metaKeywords,
            'og_title'         => $metaTitle,
            'og_description'   => $metaDescription,
        ]);
    }

    /**
     * Delete seo_meta and translations for a deleted entity.
     */
    public static function delete(PDO $pdo, string $entityType, int $entityId): void
    {
        $seoMetaId = self::getSeoMetaId($pdo, $entityType, $entityId);
        if ($seoMetaId) {
            $pdo->prepare("DELETE FROM seo_meta_translations WHERE seo_meta_id = ?")
                ->execute([$seoMetaId]);
        }
        $pdo->prepare("DELETE FROM seo_meta WHERE entity_type = ? AND entity_id = ?")
            ->execute([$entityType, $entityId]);
    }

    // ─── Private helpers ───────────────────────────────────

    private static function getSeoMetaId(PDO $pdo, string $entityType, int $entityId): ?int
    {
        $stmt = $pdo->prepare(
            "SELECT id FROM seo_meta WHERE entity_type = ? AND entity_id = ? LIMIT 1"
        );
        $stmt->execute([$entityType, $entityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    private static function upsertTranslation(PDO $pdo, int $seoMetaId, string $langCode, array $fields): void
    {
        $sql = "INSERT INTO seo_meta_translations
                    (seo_meta_id, language_code, meta_title, meta_description, meta_keywords, og_title, og_description)
                VALUES
                    (:seo_meta_id, :language_code, :meta_title, :meta_description, :meta_keywords, :og_title, :og_description)
                ON DUPLICATE KEY UPDATE
                    meta_title       = VALUES(meta_title),
                    meta_description = VALUES(meta_description),
                    meta_keywords    = VALUES(meta_keywords),
                    og_title         = VALUES(og_title),
                    og_description   = VALUES(og_description),
                    updated_at       = NOW()";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':seo_meta_id'      => $seoMetaId,
            ':language_code'    => $langCode,
            ':meta_title'       => $fields['meta_title'],
            ':meta_description' => $fields['meta_description'],
            ':meta_keywords'    => $fields['meta_keywords'],
            ':og_title'         => $fields['og_title'],
            ':og_description'   => $fields['og_description'],
        ]);
    }

    private static function generateKeywords(string $name, string $description): string
    {
        $text  = $name . ' ' . $description;
        $text  = strip_tags($text);
        $words = preg_split('/[\s,;.!?\-_\/\\\\]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_filter($words, function ($w) {
            return mb_strlen($w) > 2;
        });
        $words = array_unique(array_map('mb_strtolower', $words));
        return mb_substr(implode(', ', array_slice($words, 0, 15)), 0, 255);
    }

    private static function schemaType(string $entityType): string
    {
        $map = [
            'entity'   => 'LocalBusiness',
            'product'  => 'Product',
            'category' => 'ItemList',
            'page'     => 'WebPage',
        ];
        return $map[$entityType] ?? 'Thing';
    }
}
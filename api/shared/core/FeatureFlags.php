<?php
declare(strict_types=1);

final class FeatureFlags
{
    private static bool $initialized = false;

    private function __construct() {}
    private function __clone() {}

    /* =========================
     * Bootstrap
     * ========================= */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        if (class_exists('CacheManager')) {
            CacheManager::init();
        }

        self::$initialized = true;
    }

    /* =========================
     * Check feature
     * ========================= */
    public static function isEnabled(
        string $feature,
        string $version = 'v1',
        ?int $tenantId = null
    ): bool {
        $cacheKey = self::cacheKey($feature, $version, $tenantId);

        if (class_exists('CacheManager') && CacheManager::has($cacheKey)) {
            return (bool) CacheManager::get($cacheKey);
        }

        $pdo = DatabaseConnection::getConnection();

        $sql = "
            SELECT enabled
            FROM feature_flags
            WHERE feature = :feature
              AND version = :version
              AND tenant_id " . ($tenantId === null ? 'IS NULL' : '= :tenant_id') . "
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':feature', $feature);
        $stmt->bindValue(':version', $version);

        if ($tenantId !== null) {
            $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $value = $stmt->fetchColumn();

        if ($value === false) {
            $defaults = self::getDefaultFlags();
            $enabled = (bool) ($defaults[$feature] ?? false);
        } else {
            $enabled = (bool) $value;
        }

        if (class_exists('CacheManager')) {
            CacheManager::set($cacheKey, $enabled, 300);
        }

        return $enabled;
    }

    /* =========================
     * Enable / Disable
     * ========================= */
    public static function setEnabled(
        string $feature,
        bool $enabled,
        string $version = 'v1',
        ?int $tenantId = null
    ): void {
        $pdo = DatabaseConnection::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO feature_flags (feature, version, tenant_id, enabled, updated_at)
            VALUES (:feature, :version, :tenant_id, :enabled, NOW())
            ON DUPLICATE KEY UPDATE
                enabled = VALUES(enabled),
                updated_at = NOW()
        ");

        $stmt->execute([
            ':feature'   => $feature,
            ':version'   => $version,
            ':tenant_id' => $tenantId,
            ':enabled'   => $enabled ? 1 : 0,
        ]);

        self::clearCache($feature, $version, $tenantId);

        if (class_exists('EventDispatcher')) {
            EventDispatcher::dispatch('feature.updated', [
                'feature'  => $feature,
                'version'  => $version,
                'tenantId' => $tenantId,
                'enabled'  => $enabled,
            ]);
        }
    }

    /* =========================
     * Get all
     * ========================= */
    public static function getAll(string $version = 'v1', ?int $tenantId = null): array
    {
        $pdo = DatabaseConnection::getConnection();

        $sql = "
            SELECT feature, enabled
            FROM feature_flags
            WHERE version = :version
              AND tenant_id " . ($tenantId === null ? 'IS NULL' : '= :tenant_id');

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':version', $version);

        if ($tenantId !== null) {
            $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        }

        $stmt->execute();

        $flags = self::getDefaultFlags();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $flags[$row['feature']] = (bool) $row['enabled'];
        }

        return $flags;
    }

    /* =========================
     * Defaults
     * ========================= */
    public static function getDefaultFlags(): array
    {
        return [
            'auctions'           => true,
            'delivery'           => true,
            'jobs'               => true,
            'suppliers'          => false,
            'ai_recommendations' => true,
            'multi_currency'     => false,
        ];
    }

    /* =========================
     * Helpers
     * ========================= */
    private static function cacheKey(string $feature, string $version, ?int $tenantId): string
    {
        return 'feature:' . $version . ':' . ($tenantId ?? 'global') . ':' . $feature;
    }

    private static function clearCache(string $feature, string $version, ?int $tenantId): void
    {
        if (!class_exists('CacheManager')) {
            return;
        }

        CacheManager::delete(self::cacheKey($feature, $version, $tenantId));
    }
}

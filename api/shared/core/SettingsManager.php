<?php
declare(strict_types=1);

final class SettingsManager
{
    private function __construct() {}
    private function __clone() {}

    /* =========================
     * Get setting
     * ========================= */
    public static function get(string $key, ?int $tenantId = null)
    {
        $cacheKey = self::cacheKey($key, $tenantId);

        if (class_exists('CacheManager') && CacheManager::has($cacheKey)) {
            return CacheManager::get($cacheKey);
        }

        $pdo = DatabaseConnection::getConnection();

        $sql = "
            SELECT value
            FROM system_settings
            WHERE `key` = :key
              AND tenant_id " . ($tenantId ? "= :tenant" : "IS NULL") . "
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':key', $key);

        if ($tenantId) {
            $stmt->bindValue(':tenant', $tenantId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $value = $row ? json_decode($row['value'], true) : null;

        if (class_exists('CacheManager')) {
            CacheManager::set($cacheKey, $value, 300);
        }

        return $value;
    }

    /* =========================
     * Set setting
     * ========================= */
    public static function set(string $key, $value, ?int $tenantId = null): bool
    {
        $pdo = DatabaseConnection::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO system_settings (`key`, value, tenant_id, updated_at)
            VALUES (:key, :value, :tenant, NOW())
            ON DUPLICATE KEY UPDATE
                value = VALUES(value),
                updated_at = NOW()
        ");

        $result = $stmt->execute([
            ':key'    => $key,
            ':value'  => json_encode($value, JSON_UNESCAPED_UNICODE),
            ':tenant' => $tenantId,
        ]);

        self::invalidateCache($key, $tenantId);

        if (class_exists('EventDispatcher')) {
            EventDispatcher::dispatch('settings.updated', [
                'key' => $key,
                'tenant_id' => $tenantId,
                'value' => $value,
            ]);
        }

        return $result;
    }

    /* =========================
     * Get all
     * ========================= */
    public static function getAll(?int $tenantId = null): array
    {
        $pdo = DatabaseConnection::getConnection();

        $sql = "
            SELECT `key`, value
            FROM system_settings
            WHERE tenant_id " . ($tenantId ? "= :tenant" : "IS NULL");

        $stmt = $pdo->prepare($sql);

        if ($tenantId) {
            $stmt->bindValue(':tenant', $tenantId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = json_decode($row['value'], true);
        }

        return $settings;
    }

    /* =========================
     * Delete
     * ========================= */
    public static function delete(string $key, ?int $tenantId = null): bool
    {
        $pdo = DatabaseConnection::getConnection();

        $stmt = $pdo->prepare("
            DELETE FROM system_settings
            WHERE `key` = :key
              AND tenant_id " . ($tenantId ? "= :tenant" : "IS NULL")
        );

        $stmt->bindValue(':key', $key);
        if ($tenantId) {
            $stmt->bindValue(':tenant', $tenantId, PDO::PARAM_INT);
        }

        $result = $stmt->execute();

        self::invalidateCache($key, $tenantId);

        return $result;
    }

    /* =========================
     * Defaults
     * ========================= */
    public static function defaults(): array
    {
        return [
            'commission_rate' => 5.0,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'payment_methods' => ['stripe', 'paypal'],
            'features' => [
                'auctions' => true,
                'delivery' => true,
            ],
        ];
    }

    public static function applyDefaults(?int $tenantId = null): void
    {
        foreach (self::defaults() as $key => $value) {
            self::set($key, $value, $tenantId);
        }
    }

    /* =========================
     * Cache helpers
     * ========================= */
    private static function cacheKey(string $key, ?int $tenantId): string
    {
        return 'settings:' . $key . ':' . ($tenantId ?? 'global');
    }

    private static function invalidateCache(string $key, ?int $tenantId): void
    {
        if (class_exists('CacheManager')) {
            CacheManager::delete(self::cacheKey($key, $tenantId));
        }
    }
}

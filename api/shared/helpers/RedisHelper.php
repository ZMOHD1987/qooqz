<?php
declare(strict_types=1);

final class RedisHelper
{
    private static ?Redis $redis = null;
    private static bool $available = false;
    private static bool $initTried = false;

    /**
     * تهيئة اتصال Redis (Lazy Initialization)
     * تعيد false بشكل آمن إذا لم يكن Redis متاحاً
     */
    public static function init(): bool
    {
        if (self::$initTried) {
            return self::$available;
        }

        self::$initTried = true;

        // التأكد من تحميل امتداد redis
        if (!extension_loaded('redis')) {
            self::$available = false;
            safe_log('info', 'Redis extension not loaded - Redis disabled');
            return false;
        }

        try {
            $config = function_exists('redis_config') ? redis_config() : [
                'host'     => getenv('REDIS_HOST') ?: '127.0.0.1',
                'port'     => (int)(getenv('REDIS_PORT') ?: 6379),
                'password' => getenv('REDIS_PASSWORD') ?: '',
                'db'       => (int)(getenv('REDIS_DB') ?: 0),
            ];

            $redis = new Redis();
            $redis->connect($config['host'], (int)$config['port'], 2.5);

            if (!empty($config['password'])) {
                $redis->auth($config['password']);
            }

            $redis->select((int)$config['db']);
            
            // Test connection
            if ($redis->ping() !== 'PONG') {
                throw new Exception('Redis ping failed');
            }
            
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
            $redis->setOption(Redis::OPT_PREFIX, getenv('REDIS_PREFIX') ?: 'api:');

            self::$redis = $redis;
            self::$available = true;
            
            safe_log('info', 'Redis connection established successfully');

        } catch (Throwable $e) {
            self::$available = false;
            self::$redis = null;
            safe_log('warning', 'Redis connection failed - falling back to session storage', [
                'error' => $e->getMessage(),
                'host' => $config['host'] ?? 'unknown',
                'port' => $config['port'] ?? 'unknown'
            ]);
        }

        return self::$available;
    }

    /**
     * هل Redis متاح؟
     */
    public static function isAvailable(): bool
    {
        return self::init();
    }

    /**
     * جلب قيمة - تعيد null إذا لم يكن Redis متاحاً
     */
    public static function get(string $key): mixed
    {
        if (!self::init()) {
            return null;
        }

        try {
            $value = self::$redis->get($key);
            return $value === false ? null : $value;
        } catch (Throwable $e) {
            safe_log('warning', 'Redis get failed', ['key' => $key, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * تخزين قيمة مع TTL - تعيد false إذا لم يكن Redis متاحاً
     */
    public static function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        if (!self::init()) {
            return false;
        }

        try {
            return self::$redis->set($key, $value, ['ex' => $ttl]);
        } catch (Throwable $e) {
            safe_log('warning', 'Redis set failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * حذف مفتاح - تعيد false إذا لم يكن Redis متاحاً
     */
    public static function delete(string $key): bool
    {
        if (!self::init()) {
            return false;
        }

        try {
            return self::$redis->del($key) > 0;
        } catch (Throwable $e) {
            safe_log('warning', 'Redis delete failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * الواجهة الأساسية الموصى بها
     * تعيد null بشكل آمن إذا لم يكن Redis متاحاً
     */
    public static function instance(): ?Redis
    {
        if (!self::init()) {
            return null;
        }
        
        return self::$redis;
    }

    /**
     * Alias للتوافق مع كود قديم
     * @deprecated استخدم instance()
     */
    public static function getInstance(): ?Redis
    {
        return self::instance();
    }

    /**
     * تنظيف الاتصال
     */
    public static function close(): void
    {
        if (self::$redis) {
            try {
                self::$redis->close();
            } catch (Throwable $e) {
                // Ignore
            }
            self::$redis = null;
            self::$available = false;
            self::$initTried = false;
        }
    }

    /**
     * إعادة تهيئة الاتصال (للاختبارات)
     */
    public static function reset(): void
    {
        self::close();
    }
}
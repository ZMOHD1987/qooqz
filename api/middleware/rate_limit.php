<?php
declare(strict_types=1);

final class RateLimiter
{
    private static array $memoryCache = [];

    /**
     * تحقق من الحد المسموح للطلب
     *
     * @param string $key مفتاح مميز لكل مستخدم أو IP
     * @param int $limit عدد الطلبات المسموح بها
     * @param int $ttl الفترة الزمنية بالثواني
     * @return bool true إذا مسموح، false إذا تجاوز الحد
     */
    public static function allow(string $key, int $limit = 10, int $ttl = 60): bool
    {
        // حاول استخدام Redis أولاً
        $redis = RedisHelper::instance();

        if ($redis) {
            try {
                $current = $redis->incr($key);
                if ($current === 1) {
                    $redis->expire($key, $ttl);
                }
                return $current <= $limit;
            } catch (Throwable $e) {
                error_log('[RateLimiter] Redis error: ' . $e->getMessage());
                // fallback للذاكرة
            }
        }

        // fallback بالذاكرة (تعمل مؤقتاً لكل request)
        $now = time();
        if (!isset(self::$memoryCache[$key])) {
            self::$memoryCache[$key] = ['count' => 1, 'expires' => $now + $ttl];
            return true;
        }

        $entry = self::$memoryCache[$key];

        if ($entry['expires'] < $now) {
            // انتهاء فترة TTL، إعادة العد
            self::$memoryCache[$key] = ['count' => 1, 'expires' => $now + $ttl];
            return true;
        }

        if ($entry['count'] >= $limit) {
            return false;
        }

        self::$memoryCache[$key]['count']++;
        return true;
    }

    /**
     * إعادة تعيين العد لمفتاح معين
     */
    public static function reset(string $key): void
    {
        $redis = RedisHelper::instance();
        if ($redis) {
            try {
                $redis->del($key);
            } catch (Throwable $e) {
                // تجاهل الخطأ
            }
        }

        unset(self::$memoryCache[$key]);
    }

    /**
     * الحصول على حالة المفتاح الحالية (عدد الطلبات المتبقية)
     */
    public static function status(string $key, int $limit = 10): array
    {
        $redis = RedisHelper::instance();
        $count = 0;
        $ttl = 0;

        if ($redis) {
            try {
                $count = (int) $redis->get($key);
                $ttl = (int) $redis->ttl($key);
            } catch (Throwable $e) {
                // fallback للذاكرة
            }
        }

        if ($count === 0 && isset(self::$memoryCache[$key])) {
            $entry = self::$memoryCache[$key];
            $count = $entry['count'];
            $ttl = max(0, $entry['expires'] - time());
        }

        return [
            'remaining' => max(0, $limit - $count),
            'used' => $count,
            'ttl' => $ttl,
        ];
    }
}

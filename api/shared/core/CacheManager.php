<?php
declare(strict_types=1);

class CacheManager
{
    private static ?CacheManager $instance = null;
    private static ?Redis $redis = null;
    private static array $memory = [];
    private static array $fileCache = [];
    private static bool $initialized = false;
    private static string $prefix = 'app:';
    private static string $fileCacheDir = '';
    private static array $stats = ['hits' => 0, 'misses' => 0, 'sets' => 0];
    private static int $memoryMaxSize = 1000; // Max memory items
    private static bool $compression = false;

    private function __construct()
    {
        // Private constructor for singleton
    }

    public static function getInstance(): CacheManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::init();
        }
        return self::$instance;
    }

    private static function init(): void
    {
        if (self::$initialized) return;
        self::$initialized = true;
        
        self::$fileCacheDir = BASE_DIR . '/cache/';
        self::$compression = defined('CACHE_COMPRESSION') && CACHE_COMPRESSION;

        if (!is_dir(self::$fileCacheDir)) {
            @mkdir(self::$fileCacheDir, 0755, true);
        }

        if (!class_exists('Redis')) {
            return;
        }

        try {
            $redis = new Redis();
            $redis->connect(
                REDIS_HOST ?? '127.0.0.1',
                REDIS_PORT ?? 6379,
                REDIS_TIMEOUT ?? 1.5
            );

            if (defined('REDIS_AUTH') && REDIS_AUTH) {
                $redis->auth(REDIS_AUTH);
            }

            if (defined('REDIS_DB')) {
                $redis->select(REDIS_DB);
            }

            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            $redis->setOption(Redis::OPT_PREFIX, self::$prefix);
            
            if (self::$compression) {
                $redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZF);
            }

            self::$redis = $redis;

        } catch (Throwable $e) {
            error_log('CacheManager Redis failed: ' . $e->getMessage());
            self::$redis = null;
        }
    }

    public function get(string $key): mixed
    {
        // 1. Check memory cache first
        if (isset(self::$memory[$key])) {
            self::$stats['hits']++;
            return self::$memory[$key]['data'];
        }

        // 2. Check Redis
        if (self::$redis) {
            $value = self::$redis->get($key);
            if ($value !== false) {
                self::$stats['hits']++;
                self::storeInMemory($key, $value, 300);
                return $value;
            }
        }

        // 3. Check file cache
        $fileValue = self::getFromFile($key);
        if ($fileValue !== null) {
            self::$stats['hits']++;
            if (self::$redis) {
                self::$redis->setex($key, 1800, $fileValue);
            }
            self::storeInMemory($key, $fileValue, 300);
            return $fileValue;
        }

        self::$stats['misses']++;
        return null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600, array $tags = []): void
    {
        self::$stats['sets']++;

        self::storeInMemory($key, $value, min($ttl, 300));
        
        if (self::$redis) {
            self::$redis->setex($key, $ttl, $value);
        }
        
        self::storeInFile($key, $value, $ttl);

        if (!empty($tags)) {
            self::addTags($key, $tags);
        }
    }

    public function delete(string $key): void
    {
        unset(self::$memory[$key]);
        
        if (self::$redis) {
            self::$redis->del($key);
        }
        
        self::deleteFromFile($key);
        self::removeFromTags($key);
    }

    public function has(string $key): bool
    {
        return isset(self::$memory[$key]) || 
               (self::$redis && self::$redis->exists($key)) || 
               self::fileExists($key);
    }

    public function clear(): void
    {
        self::$memory = [];
        
        if (self::$redis) {
            $it = null;
            while ($keys = self::$redis->scan($it, self::$prefix . '*', 100)) {
                foreach ($keys as $key) {
                    self::$redis->del($key);
                }
            }
        }
        
        self::clearFileCache();
    }

    // Helper methods (keep existing ones)
    private static function storeInMemory(string $key, mixed $value, int $ttl): void
    {
        if (count(self::$memory) >= self::$memoryMaxSize) {
            array_shift(self::$memory);
        }
        
        self::$memory[$key] = [
            'data' => $value,
            'expires' => time() + $ttl
        ];
    }

    private static function getFromFile(string $key): mixed
    {
        $file = self::$fileCacheDir . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = @unserialize(file_get_contents($file));
        if (!$data || !isset($data['expires']) || $data['expires'] < time()) {
            @unlink($file);
            return null;
        }
        
        return $data['data'];
    }

    private static function storeInFile(string $key, mixed $value, int $ttl): void
    {
        $file = self::$fileCacheDir . md5($key) . '.cache';
        $data = [
            'data' => $value,
            'expires' => time() + $ttl
        ];
        
        file_put_contents($file, serialize($data), LOCK_EX);
    }

    private static function deleteFromFile(string $key): void
    {
        $file = self::$fileCacheDir . md5($key) . '.cache';
        @unlink($file);
    }

    private static function fileExists(string $key): bool
    {
        $file = self::$fileCacheDir . md5($key) . '.cache';
        return file_exists($file);
    }

    private static function clearFileCache(): void
    {
        $files = glob(self::$fileCacheDir . '*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    private static function addTags(string $key, array $tags): void
    {
        foreach ($tags as $tag) {
            $tagKey = 'tag:' . $tag;
            
            if (self::$redis) {
                self::$redis->sadd($tagKey, $key);
                self::$redis->expire($tagKey, 86400);
            } else {
                if (!isset(self::$memory[$tagKey])) {
                    self::$memory[$tagKey] = [];
                }
                self::$memory[$tagKey][] = $key;
            }
        }
    }

    private static function removeFromTags(string $key): void
    {
        if (self::$redis) {
            $it = null;
            while ($tagKeys = self::$redis->scan($it, 'tag:*', 100)) {
                foreach ($tagKeys as $tagKey) {
                    self::$redis->srem($tagKey, $key);
                }
            }
        } else {
            foreach (self::$memory as $tagKey => $keys) {
                if (str_starts_with($tagKey, 'tag:')) {
                    $index = array_search($key, $keys);
                    if ($index !== false) {
                        unset(self::$memory[$tagKey][$index]);
                    }
                }
            }
        }
    }

    public function invalidateByTag(string $tag): void
    {
        $tagKey = 'tag:' . $tag;
        $keys = [];
        
        if (self::$redis) {
            $keys = self::$redis->smembers($tagKey);
            self::$redis->del($tagKey);
        } elseif (isset(self::$memory[$tagKey])) {
            $keys = self::$memory[$tagKey];
            unset(self::$memory[$tagKey]);
        }
        
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }

    public function getStats(): array
    {
        return self::$stats;
    }

    public function getHitRate(): float
    {
        $total = self::$stats['hits'] + self::$stats['misses'];
        return $total > 0 ? round(self::$stats['hits'] / $total * 100, 2) : 0.0;
    }
}
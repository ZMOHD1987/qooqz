<?php
declare(strict_types=1);

final class ConfigLoader
{
    private static array $config = [];
    private static array $loadedFiles = [];
    private static bool $locked = false;

    /**
     * Load a config file ONCE
     */
    public static function load(string $file): void
    {
        if (self::$locked) {
            throw new RuntimeException('Config is locked. Cannot load new files.');
        }

        $real = realpath($file);
        if ($real === false || !is_readable($real)) {
            throw new RuntimeException("Config file not readable: {$file}");
        }

        if (isset(self::$loadedFiles[$real])) {
            return; // already loaded
        }

        $data = require $real;

        if (!is_array($data)) {
            throw new RuntimeException("Config file must return array: {$file}");
        }

        self::$config = self::mergeRecursive(self::$config, $data);
        self::$loadedFiles[$real] = true;
    }

    /**
     * Dot-notation getter
     */
    public static function get(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $value = self::$config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set ONLY before lock
     */
    public static function set(string $key, $value): void
    {
        if (self::$locked) {
            throw new RuntimeException('Config is locked. Cannot modify.');
        }

        $segments = explode('.', $key);
        $config = &self::$config;

        foreach ($segments as $segment) {
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }

        $config = $value;
    }

    /**
     * Lock config after bootstrap
     */
    public static function lock(): void
    {
        self::$locked = true;
    }

    public static function all(): array
    {
        return self::$config;
    }

    /* =========================
     * Internals
     * ========================= */

    private static function mergeRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::mergeRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}

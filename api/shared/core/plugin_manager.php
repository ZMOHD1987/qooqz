<?php
declare(strict_types=1);

/**
 * SAFE Plugin Manager
 * - No auto boot
 * - No container usage
 * - No DB usage
 * - Kernel-safe
 */

class PluginManager
{
    /** @var class-string[] */
    private static array $plugins = [];

    private static bool $loaded = false;

    /**
     * Load plugin classes only (NO execution)
     */
    public static function load(string $pluginDir): void
    {
        if (self::$loaded) {
            return;
        }

        if (!is_dir($pluginDir)) {
            return;
        }

        foreach (scandir($pluginDir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            $path = $pluginDir . '/' . $file;

            try {
                require_once $path;
                $class = pathinfo($file, PATHINFO_FILENAME);

                if (class_exists($class)) {
                    self::$plugins[] = $class;
                }
            } catch (Throwable $e) {
                Logger::error(
                    'Plugin load failed: ' . $file . ' | ' . $e->getMessage()
                );
            }
        }

        self::$loaded = true;
    }

    /**
     * Call hook safely AFTER kernel boot
     */
    public static function runHook(string $hook, mixed $payload = null): mixed
    {
        foreach (self::$plugins as $plugin) {
            if (!method_exists($plugin, $hook)) {
                continue;
            }

            try {
                $payload = call_user_func([$plugin, $hook], $payload);
            } catch (Throwable $e) {
                Logger::error(
                    "Plugin {$plugin}::{$hook} failed | " . $e->getMessage()
                );
            }
        }

        return $payload;
    }

    /**
     * Optional: boot plugins AFTER context is ready
     */
    public static function boot(): void
    {
        foreach (self::$plugins as $plugin) {
            if (method_exists($plugin, 'boot')) {
                try {
                    call_user_func([$plugin, 'boot']);
                } catch (Throwable $e) {
                    Logger::error(
                        "Plugin {$plugin}::boot failed | " . $e->getMessage()
                    );
                }
            }
        }
    }
}

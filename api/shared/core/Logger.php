<?php
declare(strict_types=1);

class Logger
{
    private static string $logFile = '';
    private static string $requestId = '';
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) return;
        
        self::$logFile = BASE_DIR . '/logs/app.log';
        self::$initialized = true;
        
        // Create log directory if not exists
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public static function setLogFile(string $file): void
    {
        self::$logFile = $file;
        self::init();
    }

    public static function setRequestId(string $requestId): void
    {
        self::$requestId = $requestId;
    }

    public static function getRequestId(): string
    {
        return self::$requestId ?: 'unknown';
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $context = array_merge($context, [
            'request_id' => self::getRequestId(),
            'memory_mb' => round(memory_get_usage(true) / 1048576, 2),
            'timestamp' => $timestamp
        ]);
        $contextStr = $context ? ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$timestamp}] [{$level}] [RID:" . self::getRequestId() . "] {$message}{$contextStr}\n";

        try {
            if (is_writable(dirname(self::$logFile))) {
                file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
            } else {
                error_log($line);
            }
        } catch (Throwable $e) {
            error_log('Logger failed: ' . $e->getMessage() . ' | ' . $line);
        }
    }

    // Convenience methods
    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if (defined('IS_DEBUG') && IS_DEBUG) {
            self::log('debug', $message, $context);
        }
    }
}
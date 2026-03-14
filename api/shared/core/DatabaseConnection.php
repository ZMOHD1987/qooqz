<?php
declare(strict_types=1);

final class DatabaseConnection
{
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        // ── 1. Try environment variables (Docker / .env via putenv) ──────
        $host    = getenv('DB_HOST') ?: null;
        $db      = getenv('DB_NAME') ?: null;
        $user    = getenv('DB_USER') ?: null;
        $pass    = getenv('DB_PASS') ?: null;
        $port    = (int)(getenv('DB_PORT') ?: 3306);
        $charset = 'utf8mb4';

        // ── 2. Fallback: load from db.php config file ────────────────────
        // On shared hosting (LiteSpeed / cPanel), putenv() values from .env
        // are often unavailable. We read db.php directly instead.
        if (!$host || !$db || !$user) {
            $candidates = [
                defined('BASE_DIR')   ? BASE_DIR . '/shared/config/db.php'   : null,
                defined('API_BASE_PATH') ? API_BASE_PATH . '/shared/config/db.php' : null,
                __DIR__  . '/../../shared/config/db.php',   // shared/core → shared/config
                __DIR__  . '/../config/db.php',
                ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/db.php',
            ];

            foreach ($candidates as $path) {
                if ($path && is_readable($path)) {
                    $conf = require $path;
                    if (is_array($conf) && !empty($conf['host'])) {
                        $host    = $conf['host'];
                        $db      = $conf['name'];
                        $user    = $conf['user'];
                        $pass    = $conf['pass'] ?? '';
                        $port    = (int)($conf['port'] ?? 3306);
                        $charset = $conf['charset'] ?? 'utf8mb4';
                        break;
                    }
                }
            }
        }

        // ── 3. Final check ────────────────────────────────────────────────
        if (!$host || !$db || !$user) {
            throw new RuntimeException(
                'Database configuration missing: neither environment variables nor db.php config found.'
            );
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

        self::$pdo = new PDO(
            $dsn,
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
            ]
        );

        return self::$pdo;
    }
}
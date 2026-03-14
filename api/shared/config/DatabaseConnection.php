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

        $host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : '');
        $db   = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : '');
        $user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : '');
        $pass = getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : '');
        $port = getenv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : 3306);
        $charset = 'utf8mb4';

        if (!$host || !$db || !$user) {
            throw new RuntimeException('Database environment variables missing');
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
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}"
            ]
        );

        return self::$pdo;
    }
}
<?php
declare(strict_types=1);

abstract class BaseModel
{
    /**
     * Shared PDO instance injected from bootstrap
     */
    protected static ?PDO $sharedPDO = null;

    /**
     * Instance-level PDO reference
     */
    protected PDO $db;

    /**
     * Called once from bootstrap.php
     */
    public static function setPDO(PDO $pdo): void
    {
        self::$sharedPDO = $pdo;
    }

    /**
     * All models use the shared PDO
     */
    public function __construct()
    {
        if (!self::$sharedPDO instanceof PDO) {
            throw new RuntimeException(
                'BaseModel PDO not initialized. Call BaseModel::setPDO() in bootstrap first.'
            );
        }

        $this->db = self::$sharedPDO;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data === false ? null : $data;
    }

    protected function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    protected function lastInsertId(): string
    {
        return $this->db->lastInsertId();
    }
}

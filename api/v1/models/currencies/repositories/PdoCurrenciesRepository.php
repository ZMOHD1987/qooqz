<?php
declare(strict_types=1);

final class PdoCurrenciesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        $sql = "SELECT * FROM currencies WHERE 1=1";
        $params = [];

        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = :is_active";
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (code LIKE :search OR name LIKE :search OR symbol LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY name";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
            $params[':offset'] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM currencies WHERE 1=1";
        $params = [];

        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = :is_active";
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (code LIKE :search OR name LIKE :search OR symbol LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM currencies WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): string
    {
        $existing = $this->findByCode($data['code']);

        if ($existing) {
            $stmt = $this->pdo->prepare("
                UPDATE currencies
                SET name = :name, symbol = :symbol, is_active = :is_active
                WHERE code = :code
            ");
            $stmt->execute([
                ':name' => $data['name'],
                ':symbol' => $data['symbol'] ?? null,
                ':is_active' => (int)($data['is_active'] ?? 1),
                ':code' => $data['code']
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO currencies (code, name, symbol, is_active)
                VALUES (:code, :name, :symbol, :is_active)
            ");
            $stmt->execute([
                ':code' => $data['code'],
                ':name' => $data['name'],
                ':symbol' => $data['symbol'] ?? null,
                ':is_active' => (int)($data['is_active'] ?? 1)
            ]);
        }

        return $data['code'];
    }

    public function delete(string $code): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM currencies WHERE code = :code");
        return $stmt->execute([':code' => $code]);
    }
}
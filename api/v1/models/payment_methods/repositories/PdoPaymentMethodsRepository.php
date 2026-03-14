<?php
declare(strict_types=1);

final class PdoPaymentMethodsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'method_key', 'method_name', 'gateway_name', 'created_at'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'ASC'
    ): array {
        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $where  = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = '(method_key LIKE :search OR method_name LIKE :search OR gateway_name LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql = 'SELECT * FROM payment_methods';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null)  $sql .= ' LIMIT :limit';
        if ($offset !== null) $sql .= ' OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        if ($limit !== null)  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countSql = 'SELECT COUNT(*) FROM payment_methods';
        if ($where) {
            $countSql .= ' WHERE ' . implode(' AND ', $where);
        }
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        return [
            'items' => $items,
            'meta'  => [
                'total'       => $total,
                'limit'       => $limit,
                'offset'      => $offset,
                'total_pages' => ($limit !== null && $limit > 0) ? (int)ceil($total / $limit) : 1,
            ],
        ];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payment_methods WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO payment_methods (method_key, method_name, description, gateway_name, icon_url, config)
            VALUES (:method_key, :method_name, :description, :gateway_name, :icon_url, :config)
        ');
        $stmt->execute([
            ':method_key'   => $data['method_key'],
            ':method_name'  => $data['method_name'],
            ':description'  => $data['description'] ?? null,
            ':gateway_name' => $data['gateway_name'] ?? null,
            ':icon_url'     => $data['icon_url'] ?? null,
            ':config'       => $data['config'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE payment_methods SET
                method_key   = :method_key,
                method_name  = :method_name,
                description  = :description,
                gateway_name = :gateway_name,
                icon_url     = :icon_url,
                config       = :config
            WHERE id = :id
        ');
        return $stmt->execute([
            ':method_key'   => $data['method_key'],
            ':method_name'  => $data['method_name'],
            ':description'  => $data['description'] ?? null,
            ':gateway_name' => $data['gateway_name'] ?? null,
            ':icon_url'     => $data['icon_url'] ?? null,
            ':config'       => $data['config'] ?? null,
            ':id'           => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM payment_methods WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}

<?php
declare(strict_types=1);

final class PdoEscrowLedgerRepository implements EscrowLedgerRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'escrow_ledger';
    private const ALLOWED_ORDER_BY = ['id', 'created_at', 'amount', 'transaction_type'];
    private const FILTERABLE_COLUMNS = ['escrow_id', 'entity_id', 'transaction_type', 'currency_code'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql = "SELECT * FROM " . self::TABLE . " WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND {$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM " . self::TABLE . " WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND {$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM " . self::TABLE . " WHERE tenant_id = :tenant_id AND id = :id LIMIT 1"
        );
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $params = [
            ':escrow_id'        => (int)$data['escrow_id'],
            ':entity_id'        => (int)$data['entity_id'],
            ':entity_type'      => $data['entity_type'],
            ':transaction_type' => $data['transaction_type'],
            ':amount'           => $data['amount'],
            ':currency_code'    => $data['currency_code'] ?? 'USD',
            ':notes'            => $data['notes'] ?? null,
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                tenant_id, escrow_id, entity_id, entity_type,
                transaction_type, amount, currency_code, notes
            ) VALUES (
                :tenant_id, :escrow_id, :entity_id, :entity_type,
                :transaction_type, :amount, :currency_code, :notes
            )
        ");
        $params[':tenant_id'] = $tenantId;
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM " . self::TABLE . " WHERE id = :id AND tenant_id = :tenant_id"
        );
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
    }
}

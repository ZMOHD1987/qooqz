<?php
declare(strict_types=1);

final class PdoEscrowDisputesRepository implements EscrowDisputesRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'escrow_disputes';
    private const ALLOWED_ORDER_BY = ['id', 'dispute_number', 'status', 'created_at', 'resolved_at'];
    private const FILTERABLE_COLUMNS = ['escrow_id', 'status', 'dispute_type', 'raised_by_entity_id', 'assigned_to'];

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

        if (!empty($filters['search'])) {
            $sql .= " AND (dispute_number LIKE :search OR description LIKE :search2)";
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
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

        if (!empty($filters['search'])) {
            $sql .= " AND (dispute_number LIKE :search OR description LIKE :search2)";
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
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
        $isUpdate = !empty($data['id']);

        if (!$isUpdate && empty($data['dispute_number'])) {
            $data['dispute_number'] = 'DSP-' . date('Ymd') . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        $params = [
            ':escrow_id'              => (int)$data['escrow_id'],
            ':order_id'               => isset($data['order_id']) ? (int)$data['order_id'] : null,
            ':raised_by_entity_id'    => (int)$data['raised_by_entity_id'],
            ':raised_by_entity_type'  => $data['raised_by_entity_type'],
            ':dispute_type'           => $data['dispute_type'],
            ':description'            => $data['description'],
            ':status'                 => $data['status'] ?? 'open',
            ':resolution_type'        => $data['resolution_type'] ?? null,
            ':refund_amount'          => isset($data['refund_amount']) ? $data['refund_amount'] : null,
            ':assigned_to'            => isset($data['assigned_to']) ? (int)$data['assigned_to'] : null,
            ':resolved_at'            => $data['resolved_at'] ?? null,
            ':resolution_notes'       => $data['resolution_notes'] ?? null,
        ];

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    escrow_id             = :escrow_id,
                    order_id              = :order_id,
                    raised_by_entity_id   = :raised_by_entity_id,
                    raised_by_entity_type = :raised_by_entity_type,
                    dispute_type          = :dispute_type,
                    description           = :description,
                    status                = :status,
                    resolution_type       = :resolution_type,
                    refund_amount         = :refund_amount,
                    assigned_to           = :assigned_to,
                    resolved_at           = :resolved_at,
                    resolution_notes      = :resolution_notes
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            $params[':id']        = (int)$data['id'];
            $params[':tenant_id'] = $tenantId;
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                tenant_id, dispute_number, escrow_id, order_id,
                raised_by_entity_id, raised_by_entity_type,
                dispute_type, description, status,
                resolution_type, refund_amount, assigned_to, resolved_at, resolution_notes
            ) VALUES (
                :tenant_id, :dispute_number, :escrow_id, :order_id,
                :raised_by_entity_id, :raised_by_entity_type,
                :dispute_type, :description, :status,
                :resolution_type, :refund_amount, :assigned_to, :resolved_at, :resolution_notes
            )
        ");
        $params[':tenant_id']      = $tenantId;
        $params[':dispute_number'] = $data['dispute_number'];
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

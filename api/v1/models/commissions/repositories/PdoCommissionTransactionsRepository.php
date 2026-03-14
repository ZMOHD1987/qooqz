<?php
declare(strict_types=1);

/**
 * PDO repository for the commission_transactions table.
 */
final class PdoCommissionTransactionsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'order_date', 'order_amount', 'commission_amount', 'status', 'created_at'];

    private const ALLOWED_COLUMNS = [
        'tenant_id', 'entity_id', 'order_id', 'order_date', 'transaction_type',
        'order_amount', 'commission_amount', 'vat_amount', 'net_commission',
        'status', 'is_locked', 'locked_at', 'created_by', 'updated_by', 'cancelled_by',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with filters, ordering, pagination
    // ================================
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'created_at',
        string $orderDir = 'DESC'
    ): array {
        $items = $this->query($limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->count($filters);

        return [
            'items' => $items,
            'meta'  => [
                'total'       => $total,
                'limit'       => $limit,
                'offset'      => $offset,
                'total_pages' => ($limit !== null && $limit > 0) ? (int)ceil($total / $limit) : 0,
            ],
        ];
    }

    // ================================
    // Query rows
    // ================================
    private function query(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        $sql = "SELECT ct.* FROM commission_transactions ct WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'created_at';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY ct.{$orderBy} {$orderDir}";

        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        if ($limit  !== null) $stmt->bindValue(':limit',  (int)$limit,  PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Count
    // ================================
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM commission_transactions ct WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Apply filters
    // ================================
    private function applyFilters(string &$sql, array &$params, array $filters): void
    {
        if (isset($filters['tenant_id']) && $filters['tenant_id'] !== '') {
            $sql .= " AND ct.tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$filters['tenant_id'];
        }

        if (isset($filters['entity_id']) && $filters['entity_id'] !== '') {
            $sql .= " AND ct.entity_id = :entity_id";
            $params[':entity_id'] = (int)$filters['entity_id'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND ct.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (isset($filters['transaction_type']) && $filters['transaction_type'] !== '') {
            $sql .= " AND ct.transaction_type = :transaction_type";
            $params[':transaction_type'] = $filters['transaction_type'];
        }

        if (isset($filters['order_id']) && $filters['order_id'] !== '') {
            $sql .= " AND ct.order_id = :order_id";
            $params[':order_id'] = (int)$filters['order_id'];
        }

        if (isset($filters['date_from']) && $filters['date_from'] !== '') {
            $sql .= " AND ct.order_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to']) && $filters['date_to'] !== '') {
            $sql .= " AND ct.order_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM commission_transactions WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    // ================================
    // Create
    // ================================
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO commission_transactions
                (tenant_id, entity_id, order_id, order_date, transaction_type,
                 order_amount, commission_amount, vat_amount, net_commission,
                 status, is_locked, locked_at, created_by, created_at, updated_at)
            VALUES
                (:tenant_id, :entity_id, :order_id, :order_date, :transaction_type,
                 :order_amount, :commission_amount, :vat_amount, :net_commission,
                 :status, :is_locked, :locked_at, :created_by, NOW(), NOW())
        ");

        $stmt->execute([
            ':tenant_id'         => (int)$data['tenant_id'],
            ':entity_id'         => (int)$data['entity_id'],
            ':order_id'          => (int)$data['order_id'],
            ':order_date'        => $data['order_date'],
            ':transaction_type'  => $data['transaction_type'] ?? 'sale',
            ':order_amount'      => $data['order_amount'],
            ':commission_amount' => $data['commission_amount'],
            ':vat_amount'        => $data['vat_amount'] ?? 0,
            ':net_commission'    => $data['net_commission'],
            ':status'            => $data['status'] ?? 'pending',
            ':is_locked'         => (int)($data['is_locked'] ?? 0),
            ':locked_at'         => $data['locked_at'] ?? null,
            ':created_by'        => isset($data['created_by']) ? (int)$data['created_by'] : null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Update
    // ================================
    public function update(int $id, array $data): bool
    {
        $setClauses = [];
        $params = [':id' => $id];

        foreach (self::ALLOWED_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $setClauses[] = "{$col} = :{$col}";
                $params[':' . $col] = $data[$col];
            }
        }

        if (empty($setClauses)) {
            throw new InvalidArgumentException("No valid fields provided for update");
        }

        $setClauses[] = "updated_at = NOW()";
        $sql = "UPDATE commission_transactions SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("DELETE FROM commission_credit_notes WHERE related_transaction_id = :id");
            $stmt->execute([':id' => $id]);
            $stmt = $this->pdo->prepare("DELETE FROM commission_invoice_items WHERE transaction_id = :id");
            $stmt->execute([':id' => $id]);
            $stmt = $this->pdo->prepare("DELETE FROM commission_transactions WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ================================
    // Stats
    // ================================
    public function stats(?int $tenantId = null, ?int $entityId = null): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'invoiced' THEN 1 ELSE 0 END) AS invoiced,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                COALESCE(SUM(order_amount), 0) AS total_order_amount,
                COALESCE(SUM(commission_amount), 0) AS total_commission_amount,
                COALESCE(SUM(vat_amount), 0) AS total_vat_amount,
                COALESCE(SUM(net_commission), 0) AS total_net_commission
            FROM commission_transactions
            WHERE 1=1
        ";
        $params = [];

        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }

        if ($entityId !== null) {
            $sql .= " AND entity_id = :entity_id";
            $params[':entity_id'] = $entityId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'                    => (int)($row['total'] ?? 0),
            'pending'                  => (int)($row['pending'] ?? 0),
            'invoiced'                 => (int)($row['invoiced'] ?? 0),
            'paid'                     => (int)($row['paid'] ?? 0),
            'cancelled'                => (int)($row['cancelled'] ?? 0),
            'total_order_amount'       => (float)($row['total_order_amount'] ?? 0),
            'total_commission_amount'  => (float)($row['total_commission_amount'] ?? 0),
            'total_vat_amount'         => (float)($row['total_vat_amount'] ?? 0),
            'total_net_commission'     => (float)($row['total_net_commission'] ?? 0),
        ];
    }
}
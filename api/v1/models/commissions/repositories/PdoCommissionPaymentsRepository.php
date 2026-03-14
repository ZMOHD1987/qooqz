<?php
declare(strict_types=1);

/**
 * PDO repository for the commission_payments table.
 */
final class PdoCommissionPaymentsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'payment_number', 'amount_paid', 'paid_at', 'created_at'];

    private const ALLOWED_COLUMNS = [
        'tenant_id', 'entity_id', 'commission_invoice_id', 'payment_number',
        'payment_method', 'amount_paid', 'paid_at',
        'is_cancelled', 'cancelled_at', 'cancellation_reason',
        'created_by', 'cancelled_by',
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
        $sql = "SELECT cp.*, e.store_name AS entity_name
                FROM commission_payments cp
                LEFT JOIN entities e ON e.id = cp.entity_id
                WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'created_at';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY cp.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM commission_payments cp WHERE 1=1";
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
            $sql .= " AND cp.tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$filters['tenant_id'];
        }

        if (isset($filters['entity_id']) && $filters['entity_id'] !== '') {
            $sql .= " AND cp.entity_id = :entity_id";
            $params[':entity_id'] = (int)$filters['entity_id'];
        }

        if (isset($filters['commission_invoice_id']) && $filters['commission_invoice_id'] !== '') {
            $sql .= " AND cp.commission_invoice_id = :commission_invoice_id";
            $params[':commission_invoice_id'] = (int)$filters['commission_invoice_id'];
        }

        if (isset($filters['is_cancelled']) && $filters['is_cancelled'] !== '') {
            $sql .= " AND cp.is_cancelled = :is_cancelled";
            $params[':is_cancelled'] = (int)$filters['is_cancelled'];
        }

        if (isset($filters['date_from']) && $filters['date_from'] !== '') {
            $sql .= " AND cp.paid_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to']) && $filters['date_to'] !== '') {
            $sql .= " AND cp.paid_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM commission_payments WHERE id = :id LIMIT 1");
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
            INSERT INTO commission_payments
                (tenant_id, entity_id, commission_invoice_id, payment_number,
                 payment_method, amount_paid, paid_at,
                 is_cancelled, cancelled_at, cancellation_reason,
                 created_by, cancelled_by, created_at)
            VALUES
                (:tenant_id, :entity_id, :commission_invoice_id, :payment_number,
                 :payment_method, :amount_paid, :paid_at,
                 :is_cancelled, :cancelled_at, :cancellation_reason,
                 :created_by, :cancelled_by, NOW())
        ");

        $stmt->execute([
            ':tenant_id'              => (int)$data['tenant_id'],
            ':entity_id'              => (int)$data['entity_id'],
            ':commission_invoice_id'  => (int)$data['commission_invoice_id'],
            ':payment_number'         => $data['payment_number'] ?? $this->generatePaymentNumber(),
            ':payment_method'         => $data['payment_method'] ?? null,
            ':amount_paid'            => $data['amount_paid'],
            ':paid_at'                => $data['paid_at'],
            ':is_cancelled'           => (int)($data['is_cancelled'] ?? 0),
            ':cancelled_at'           => $data['cancelled_at'] ?? null,
            ':cancellation_reason'    => $data['cancellation_reason'] ?? null,
            ':created_by'             => isset($data['created_by']) ? (int)$data['created_by'] : null,
            ':cancelled_by'           => isset($data['cancelled_by']) ? (int)$data['cancelled_by'] : null,
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

        $sql = "UPDATE commission_payments SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM commission_payments WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Stats
    // ================================
    public function stats(?int $tenantId = null, ?int $entityId = null): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN is_cancelled = 0 THEN amount_paid ELSE 0 END), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN is_cancelled = 1 THEN amount_paid ELSE 0 END), 0) AS total_cancelled
            FROM commission_payments
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
            'total'           => (int)($row['total'] ?? 0),
            'total_paid'      => (float)($row['total_paid'] ?? 0),
            'total_cancelled' => (float)($row['total_cancelled'] ?? 0),
        ];
    }

    // ================================
    // Generate payment number: CPAY-YYYYMMDD-XXXXX
    // ================================
    public function generatePaymentNumber(): string
    {
        $datePrefix = 'CPAY-' . date('Ymd') . '-';

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                SELECT payment_number FROM commission_payments
                WHERE payment_number LIKE :prefix
                ORDER BY payment_number DESC
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':prefix' => $datePrefix . '%']);
            $last = $stmt->fetchColumn();

            if ($last) {
                $lastSeq = (int)substr($last, -5);
                $nextSeq = $lastSeq + 1;
            } else {
                $nextSeq = 1;
            }

            $number = $datePrefix . str_pad((string)$nextSeq, 5, '0', STR_PAD_LEFT);
            $this->pdo->commit();

            return $number;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

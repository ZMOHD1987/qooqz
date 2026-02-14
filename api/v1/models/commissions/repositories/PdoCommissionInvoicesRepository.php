<?php
declare(strict_types=1);

/**
 * PDO repository for the commission_invoices table.
 */
final class PdoCommissionInvoicesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'invoice_number', 'period_start', 'period_end', 'grand_total', 'status', 'created_at'];

    private const ALLOWED_COLUMNS = [
        'tenant_id', 'entity_id', 'invoice_number', 'invoice_type',
        'period_start', 'period_end', 'total_orders', 'total_orders_amount',
        'total_commission', 'total_vat', 'grand_total', 'amount_paid',
        'status', 'issued_at', 'due_date', 'paid_at',
        'is_locked', 'locked_at', 'created_by', 'issued_by', 'cancelled_by',
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
        $sql = "SELECT ci.*, e.store_name AS entity_name
                FROM commission_invoices ci
                LEFT JOIN entities e ON e.id = ci.entity_id
                WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'created_at';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY ci.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM commission_invoices ci WHERE 1=1";
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
            $sql .= " AND ci.tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$filters['tenant_id'];
        }

        if (isset($filters['entity_id']) && $filters['entity_id'] !== '') {
            $sql .= " AND ci.entity_id = :entity_id";
            $params[':entity_id'] = (int)$filters['entity_id'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND ci.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (isset($filters['invoice_type']) && $filters['invoice_type'] !== '') {
            $sql .= " AND ci.invoice_type = :invoice_type";
            $params[':invoice_type'] = $filters['invoice_type'];
        }

        if (isset($filters['date_from']) && $filters['date_from'] !== '') {
            $sql .= " AND ci.period_start >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to']) && $filters['date_to'] !== '') {
            $sql .= " AND ci.period_end <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM commission_invoices WHERE id = :id LIMIT 1");
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
            INSERT INTO commission_invoices
                (tenant_id, entity_id, invoice_number, invoice_type,
                 period_start, period_end, total_orders, total_orders_amount,
                 total_commission, total_vat, grand_total, amount_paid,
                 status, issued_at, due_date, paid_at,
                 is_locked, locked_at, created_by, issued_by, cancelled_by,
                 created_at, updated_at)
            VALUES
                (:tenant_id, :entity_id, :invoice_number, :invoice_type,
                 :period_start, :period_end, :total_orders, :total_orders_amount,
                 :total_commission, :total_vat, :grand_total, :amount_paid,
                 :status, :issued_at, :due_date, :paid_at,
                 :is_locked, :locked_at, :created_by, :issued_by, :cancelled_by,
                 NOW(), NOW())
        ");

        $stmt->execute([
            ':tenant_id'           => (int)$data['tenant_id'],
            ':entity_id'           => (int)$data['entity_id'],
            ':invoice_number'      => $data['invoice_number'] ?? $this->generateInvoiceNumber(),
            ':invoice_type'        => $data['invoice_type'],
            ':period_start'        => $data['period_start'],
            ':period_end'          => $data['period_end'],
            ':total_orders'        => (int)($data['total_orders'] ?? 0),
            ':total_orders_amount' => $data['total_orders_amount'] ?? 0,
            ':total_commission'    => $data['total_commission'] ?? 0,
            ':total_vat'           => $data['total_vat'] ?? 0,
            ':grand_total'         => $data['grand_total'],
            ':amount_paid'         => $data['amount_paid'] ?? 0,
            ':status'              => $data['status'] ?? 'draft',
            ':issued_at'           => $data['issued_at'] ?? null,
            ':due_date'            => $data['due_date'] ?? null,
            ':paid_at'             => $data['paid_at'] ?? null,
            ':is_locked'           => (int)($data['is_locked'] ?? 0),
            ':locked_at'           => $data['locked_at'] ?? null,
            ':created_by'          => isset($data['created_by']) ? (int)$data['created_by'] : null,
            ':issued_by'           => isset($data['issued_by']) ? (int)$data['issued_by'] : null,
            ':cancelled_by'        => isset($data['cancelled_by']) ? (int)$data['cancelled_by'] : null,
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
        $sql = "UPDATE commission_invoices SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM commission_invoices WHERE id = :id");
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
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft,
                SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) AS issued,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                COALESCE(SUM(grand_total), 0) AS total_amount,
                COALESCE(SUM(amount_paid), 0) AS total_paid
            FROM commission_invoices
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
            'total'        => (int)($row['total'] ?? 0),
            'draft'        => (int)($row['draft'] ?? 0),
            'issued'       => (int)($row['issued'] ?? 0),
            'paid'         => (int)($row['paid'] ?? 0),
            'cancelled'    => (int)($row['cancelled'] ?? 0),
            'total_amount' => (float)($row['total_amount'] ?? 0),
            'total_paid'   => (float)($row['total_paid'] ?? 0),
        ];
    }

    // ================================
    // Generate invoice number: CINV-YYYYMMDD-XXXXX
    // ================================
    public function generateInvoiceNumber(): string
    {
        $datePrefix = 'CINV-' . date('Ymd') . '-';

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                SELECT invoice_number FROM commission_invoices
                WHERE invoice_number LIKE :prefix
                ORDER BY invoice_number DESC
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

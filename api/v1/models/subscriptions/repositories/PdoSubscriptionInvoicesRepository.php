<?php
declare(strict_types=1);

/**
 * PDO repository for the subscription_invoices table.
 */
final class PdoSubscriptionInvoicesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'invoice_number', 'amount', 'total_amount', 'due_date', 'status', 'created_at'];

    private const ALLOWED_COLUMNS = [
        'invoice_number', 'subscription_id', 'tenant_id', 'amount', 'tax_amount',
        'total_amount', 'currency_code', 'billing_period_start', 'billing_period_end',
        'due_date', 'status', 'paid_at', 'payment_method', 'transaction_id', 'notes',
    ];

    private const ALLOWED_STATUSES = ['pending', 'paid', 'overdue', 'cancelled', 'refunded'];

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
        string $orderBy = 'id',
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
        $sql = "SELECT si.* FROM subscription_invoices si WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY si.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM subscription_invoices si WHERE 1=1";
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
        if (isset($filters['subscription_id']) && $filters['subscription_id'] !== '') {
            $sql .= " AND si.subscription_id = :subscription_id";
            $params[':subscription_id'] = (int)$filters['subscription_id'];
        }

        if (isset($filters['tenant_id']) && $filters['tenant_id'] !== '') {
            $sql .= " AND si.tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$filters['tenant_id'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND si.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $sql .= " AND si.invoice_number LIKE :search";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }
    }

    // ================================
    // All with tenant name JOIN and filters (for CSV export)
    // ================================
    public function all(int $limit = 25, int $offset = 0, array $filters = []): array
    {
        $sql = "SELECT i.*, t.name AS tenant_name FROM subscription_invoices i LEFT JOIN tenants t ON i.tenant_id = t.id WHERE 1=1";
        $params = [];

        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND i.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['date_from']) && $filters['date_from'] !== '') {
            $sql .= " AND i.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (isset($filters['date_to']) && $filters['date_to'] !== '') {
            $sql .= " AND i.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql .= " ORDER BY i.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM subscription_invoices WHERE id = :id LIMIT 1");
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
            INSERT INTO subscription_invoices
                (invoice_number, subscription_id, tenant_id, amount, tax_amount,
                 total_amount, currency_code, billing_period_start, billing_period_end,
                 due_date, status, payment_method, notes, created_at)
            VALUES
                (:invoice_number, :subscription_id, :tenant_id, :amount, :tax_amount,
                 :total_amount, :currency_code, :billing_period_start, :billing_period_end,
                 :due_date, :status, :payment_method, :notes, NOW())
        ");

        $stmt->execute([
            ':invoice_number'      => $data['invoice_number'] ?? ('INV-' . strtoupper(bin2hex(random_bytes(5)))),
            ':subscription_id'     => (int)$data['subscription_id'],
            ':tenant_id'           => (int)$data['tenant_id'],
            ':amount'              => $data['amount'],
            ':tax_amount'          => $data['tax_amount'] ?? 0,
            ':total_amount'        => $data['total_amount'],
            ':currency_code'       => $data['currency_code'] ?? 'SAR',
            ':billing_period_start'=> $data['billing_period_start'] ?? null,
            ':billing_period_end'  => $data['billing_period_end'] ?? null,
            ':due_date'            => $data['due_date'] ?? null,
            ':status'              => $data['status'] ?? 'pending',
            ':payment_method'      => $data['payment_method'] ?? null,
            ':notes'               => $data['notes'] ?? null,
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

        $sql = "UPDATE subscription_invoices SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Mark as paid
    // ================================
    public function markPaid(int $id, string $paymentMethod, string $transactionId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE subscription_invoices
            SET status = 'paid', paid_at = NOW(), payment_method = :payment_method,
                transaction_id = :transaction_id
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id'              => $id,
            ':payment_method'  => $paymentMethod,
            ':transaction_id'  => $transactionId,
        ]);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM subscription_invoices WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Stats
    // ================================
    public function stats(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) AS overdue,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) AS refunded,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) AS total_revenue
            FROM subscription_invoices
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'         => (int)($row['total'] ?? 0),
            'pending'       => (int)($row['pending'] ?? 0),
            'paid'          => (int)($row['paid'] ?? 0),
            'overdue'       => (int)($row['overdue'] ?? 0),
            'cancelled'     => (int)($row['cancelled'] ?? 0),
            'refunded'      => (int)($row['refunded'] ?? 0),
            'total_revenue' => (float)($row['total_revenue'] ?? 0),
        ];
    }
}
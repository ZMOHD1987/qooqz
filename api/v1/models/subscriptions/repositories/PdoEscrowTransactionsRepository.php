<?php
declare(strict_types=1);

/**
 * PDO repository for the escrow_transactions table.
 */
final class PdoEscrowTransactionsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'escrow_number', 'amount', 'status', 'created_at'];

    private const ALLOWED_COLUMNS = [
        'escrow_number', 'order_id', 'buyer_id', 'seller_id', 'seller_type',
        'amount', 'currency_code', 'escrow_fee', 'status',
        'funded_at', 'shipped_at', 'delivered_at', 'released_at',
        'disputed_at', 'refunded_at', 'cancelled_at', 'tenant_id',
    ];

    private const ALLOWED_STATUSES = ['pending', 'funded', 'in_transit', 'delivered', 'released', 'disputed', 'refunded', 'cancelled'];

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
        $sql = "SELECT et.* FROM escrow_transactions et WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY et.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM escrow_transactions et WHERE 1=1";
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
            $sql .= " AND et.tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$filters['tenant_id'];
        }

        if (isset($filters['order_id']) && $filters['order_id'] !== '') {
            $sql .= " AND et.order_id = :order_id";
            $params[':order_id'] = (int)$filters['order_id'];
        }

        if (isset($filters['buyer_id']) && $filters['buyer_id'] !== '') {
            $sql .= " AND et.buyer_id = :buyer_id";
            $params[':buyer_id'] = (int)$filters['buyer_id'];
        }

        if (isset($filters['seller_id']) && $filters['seller_id'] !== '') {
            $sql .= " AND et.seller_id = :seller_id";
            $params[':seller_id'] = (int)$filters['seller_id'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND et.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $sql .= " AND et.escrow_number LIKE :search";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM escrow_transactions WHERE id = :id LIMIT 1");
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
            INSERT INTO escrow_transactions
                (escrow_number, order_id, buyer_id, seller_id, seller_type,
                 amount, currency_code, escrow_fee, status, tenant_id,
                 created_at, updated_at)
            VALUES
                (:escrow_number, :order_id, :buyer_id, :seller_id, :seller_type,
                 :amount, :currency_code, :escrow_fee, :status, :tenant_id,
                 NOW(), NOW())
        ");

        $stmt->execute([
            ':escrow_number' => $data['escrow_number'] ?? ('ESC-' . strtoupper(bin2hex(random_bytes(5)))),
            ':order_id'      => (int)$data['order_id'],
            ':buyer_id'      => (int)$data['buyer_id'],
            ':seller_id'     => (int)$data['seller_id'],
            ':seller_type'   => $data['seller_type'] ?? 'vendor',
            ':amount'        => $data['amount'],
            ':currency_code' => $data['currency_code'] ?? 'SAR',
            ':escrow_fee'    => $data['escrow_fee'] ?? 0,
            ':status'        => $data['status'] ?? 'pending',
            ':tenant_id'     => (int)($data['tenant_id'] ?? 0),
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
        $sql = "UPDATE escrow_transactions SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Status workflow
    // ================================
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        $timestampMap = [
            'funded'    => 'funded_at',
            'in_transit'=> 'shipped_at',
            'delivered' => 'delivered_at',
            'released'  => 'released_at',
            'disputed'  => 'disputed_at',
            'refunded'  => 'refunded_at',
            'cancelled' => 'cancelled_at',
        ];

        $extra = '';
        if (isset($timestampMap[$status])) {
            $extra = ", {$timestampMap[$status]} = NOW()";
        }

        $stmt = $this->pdo->prepare("UPDATE escrow_transactions SET status = :status{$extra}, updated_at = NOW() WHERE id = :id");
        return $stmt->execute([':id' => $id, ':status' => $status]);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM escrow_transactions WHERE id = :id");
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
                SUM(CASE WHEN status = 'funded' THEN 1 ELSE 0 END) AS funded,
                SUM(CASE WHEN status = 'in_transit' THEN 1 ELSE 0 END) AS in_transit,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) AS released,
                SUM(CASE WHEN status = 'disputed' THEN 1 ELSE 0 END) AS disputed,
                COALESCE(SUM(CASE WHEN status = 'released' THEN amount ELSE 0 END), 0) AS total_released_amount
            FROM escrow_transactions
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'                 => (int)($row['total'] ?? 0),
            'pending'               => (int)($row['pending'] ?? 0),
            'funded'                => (int)($row['funded'] ?? 0),
            'in_transit'            => (int)($row['in_transit'] ?? 0),
            'delivered'             => (int)($row['delivered'] ?? 0),
            'released'              => (int)($row['released'] ?? 0),
            'disputed'              => (int)($row['disputed'] ?? 0),
            'total_released_amount' => (float)($row['total_released_amount'] ?? 0),
        ];
    }
}

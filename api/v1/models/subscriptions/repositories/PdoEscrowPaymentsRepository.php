<?php
declare(strict_types=1);

/**
 * PDO repository for the escrow_payments table.
 */
final class PdoEscrowPaymentsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'amount', 'status', 'paid_at', 'created_at'];

    private const ALLOWED_COLUMNS = [
        'escrow_id', 'amount', 'currency_code', 'payment_gateway',
        'gateway_transaction_id', 'status', 'paid_at',
    ];

    private const ALLOWED_STATUSES = ['pending', 'success', 'failed', 'refunded'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // All with filters
    // ================================
    public function all(array $filters = []): array
    {
        $limit  = isset($filters['limit'])  ? (int)$filters['limit']  : 25;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;

        $sql = "SELECT ep.* FROM escrow_payments ep WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $orderBy  = isset($filters['order_by']) && in_array($filters['order_by'], self::ALLOWED_ORDER_BY, true)
            ? $filters['order_by'] : 'id';
        $orderDir = isset($filters['order_dir']) && strtoupper($filters['order_dir']) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ep.{$orderBy} {$orderDir} LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = $this->count($filters);

        return [
            'items' => $items,
            'meta'  => [
                'total'       => $total,
                'limit'       => $limit,
                'offset'      => $offset,
                'total_pages' => $limit > 0 ? (int)ceil($total / $limit) : 0,
            ],
        ];
    }

    // ================================
    // Count
    // ================================
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM escrow_payments ep WHERE 1=1";
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
        if (isset($filters['escrow_id']) && $filters['escrow_id'] !== '') {
            $sql .= " AND ep.escrow_id = :escrow_id";
            $params[':escrow_id'] = (int)$filters['escrow_id'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND ep.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (isset($filters['date_from']) && $filters['date_from'] !== '') {
            $sql .= " AND ep.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to']) && $filters['date_to'] !== '') {
            $sql .= " AND ep.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM escrow_payments WHERE id = :id LIMIT 1");
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
            INSERT INTO escrow_payments
                (escrow_id, amount, currency_code, payment_gateway,
                 gateway_transaction_id, status, paid_at, created_at)
            VALUES
                (:escrow_id, :amount, :currency_code, :payment_gateway,
                 :gateway_transaction_id, :status, :paid_at, NOW())
        ");

        $stmt->execute([
            ':escrow_id'              => (int)$data['escrow_id'],
            ':amount'                 => $data['amount'],
            ':currency_code'          => $data['currency_code'] ?? 'SAR',
            ':payment_gateway'        => $data['payment_gateway'],
            ':gateway_transaction_id' => $data['gateway_transaction_id'] ?? null,
            ':status'                 => $data['status'] ?? 'pending',
            ':paid_at'                => $data['paid_at'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM escrow_payments WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Mark as success
    // ================================
    public function markSuccess(int $id, string $gatewayTxnId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE escrow_payments
            SET status = 'success', paid_at = NOW(),
                gateway_transaction_id = :gateway_transaction_id
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id'                     => $id,
            ':gateway_transaction_id' => $gatewayTxnId,
        ]);
    }

    // ================================
    // Mark as refunded
    // ================================
    public function markRefunded(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE escrow_payments
            SET status = 'refunded'
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Stats
    // ================================
    public function stats(array $filters = []): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS success,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) AS refunded,
                COALESCE(SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END), 0) AS total_collected
            FROM escrow_payments ep WHERE 1=1
        ";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'           => (int)($row['total'] ?? 0),
            'pending'         => (int)($row['pending'] ?? 0),
            'success'         => (int)($row['success'] ?? 0),
            'failed'          => (int)($row['failed'] ?? 0),
            'refunded'        => (int)($row['refunded'] ?? 0),
            'total_collected' => (float)($row['total_collected'] ?? 0),
        ];
    }
}

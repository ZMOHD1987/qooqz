<?php
declare(strict_types=1);

/**
 * PDO repository for the commission_invoice_items table.
 */
final class PdoCommissionInvoiceItemsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'order_date', 'order_amount', 'commission_amount', 'net_commission', 'transaction_type'];

    private const ALLOWED_COLUMNS = [
        'invoice_id', 'transaction_id', 'order_id', 'order_date',
        'order_amount', 'commission_amount', 'vat_amount', 'net_commission',
        'transaction_type',
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
        $sql = "SELECT cii.* FROM commission_invoice_items cii WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY cii.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM commission_invoice_items cii WHERE 1=1";
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
        if (isset($filters['invoice_id']) && $filters['invoice_id'] !== '') {
            $sql .= " AND cii.invoice_id = :invoice_id";
            $params[':invoice_id'] = (int)$filters['invoice_id'];
        }

        if (isset($filters['transaction_type']) && $filters['transaction_type'] !== '') {
            $sql .= " AND cii.transaction_type = :transaction_type";
            $params[':transaction_type'] = $filters['transaction_type'];
        }
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM commission_invoice_items WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    // ================================
    // List by invoice (convenience)
    // ================================
    public function listByInvoice(int $invoiceId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM commission_invoice_items WHERE invoice_id = :invoice_id ORDER BY id ASC");
        $stmt->execute([':invoice_id' => $invoiceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Create
    // ================================
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO commission_invoice_items
                (invoice_id, transaction_id, order_id, order_date,
                 order_amount, commission_amount, vat_amount, net_commission,
                 transaction_type)
            VALUES
                (:invoice_id, :transaction_id, :order_id, :order_date,
                 :order_amount, :commission_amount, :vat_amount, :net_commission,
                 :transaction_type)
        ");

        $stmt->execute([
            ':invoice_id'        => (int)$data['invoice_id'],
            ':transaction_id'    => (int)$data['transaction_id'],
            ':order_id'          => (int)$data['order_id'],
            ':order_date'        => $data['order_date'],
            ':order_amount'      => $data['order_amount'],
            ':commission_amount' => $data['commission_amount'],
            ':vat_amount'        => $data['vat_amount'] ?? 0,
            ':net_commission'    => $data['net_commission'],
            ':transaction_type'  => $data['transaction_type'],
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

        $sql = "UPDATE commission_invoice_items SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM commission_invoice_items WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}

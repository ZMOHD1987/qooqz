<?php
declare(strict_types=1);

/**
 * PDO repository for the entity_financial_balances table.
 */
final class PdoEntityFinancialBalancesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['entity_id', 'net_sales', 'total_commission', 'total_balance', 'total_paid', 'updated_at'];

    private const ALLOWED_COLUMNS = [
        'tenant_id', 'total_transactions',
        'total_sales_count', 'total_refunds_count',
        'total_sales_amount', 'total_refunds_amount', 'net_sales',
        'total_commission', 'total_vat', 'total_net_commission',
        'total_invoiced', 'total_paid', 'total_balance',
        'pending_balance', 'invoiced_balance',
        'total_invoices', 'total_payments', 'total_credit_notes',
        'last_transaction_date', 'last_invoice_date', 'last_payment_date',
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
        string $orderBy = 'entity_id',
        string $orderDir = 'ASC'
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
        $sql = "SELECT efb.*, e.store_name AS entity_name
                FROM entity_financial_balances efb
                LEFT JOIN entities e ON e.id = efb.entity_id
                WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'entity_id';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY efb.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM entity_financial_balances efb WHERE 1=1";
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
            $sql .= " AND efb.tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$filters['tenant_id'];
        }
    }

    // ================================
    // Find by entity_id
    // ================================
    public function find(int $entityId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT efb.*, e.store_name AS entity_name
            FROM entity_financial_balances efb
            LEFT JOIN entities e ON e.id = efb.entity_id
            WHERE efb.entity_id = :entity_id
            LIMIT 1
        ");
        $stmt->execute([':entity_id' => $entityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    // ================================
    // Upsert (INSERT ON DUPLICATE KEY UPDATE)
    // ================================
    public function upsert(int $entityId, array $data): bool
    {
        $columns = ['entity_id'];
        $values = [':entity_id'];
        $updates = [];
        $params = [':entity_id' => $entityId];

        foreach (self::ALLOWED_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $columns[] = $col;
                $values[] = ":{$col}";
                $updates[] = "{$col} = :{$col}";
                $params[':' . $col] = $data[$col];
            }
        }

        if (empty($updates)) {
            throw new InvalidArgumentException("No valid fields provided for upsert");
        }

        $updates[] = "updated_at = NOW()";

        $sql = "INSERT INTO entity_financial_balances (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $updates);

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Recalculate all balances from source tables
    // ================================
    public function recalculate(int $entityId): bool
    {
        $sql = "
            INSERT INTO entity_financial_balances (
                entity_id, tenant_id,
                total_transactions, total_sales_count, total_refunds_count,
                total_sales_amount, total_refunds_amount, net_sales,
                total_commission, total_vat, total_net_commission,
                total_invoiced, total_paid, total_balance,
                pending_balance, invoiced_balance,
                total_invoices, total_payments, total_credit_notes,
                last_transaction_date, last_invoice_date, last_payment_date,
                updated_at
            )
            SELECT
                ct.entity_id,
                ct.tenant_id,
                COUNT(ct.id),
                SUM(CASE WHEN ct.transaction_type = 'sale' THEN 1 ELSE 0 END),
                SUM(CASE WHEN ct.transaction_type = 'refund' THEN 1 ELSE 0 END),
                COALESCE(SUM(CASE WHEN ct.transaction_type = 'sale' THEN ct.order_amount ELSE 0 END), 0),
                COALESCE(SUM(CASE WHEN ct.transaction_type = 'refund' THEN ct.order_amount ELSE 0 END), 0),
                COALESCE(SUM(CASE WHEN ct.transaction_type = 'sale' THEN ct.order_amount ELSE -ct.order_amount END), 0),
                COALESCE(SUM(ct.commission_amount), 0),
                COALESCE(SUM(ct.vat_amount), 0),
                COALESCE(SUM(ct.net_commission), 0),
                COALESCE((SELECT SUM(ci.grand_total) FROM commission_invoices ci WHERE ci.entity_id = ct.entity_id AND ci.status != 'cancelled'), 0),
                COALESCE((SELECT SUM(cp.amount_paid) FROM commission_payments cp WHERE cp.entity_id = ct.entity_id AND cp.is_cancelled = 0), 0),
                COALESCE(SUM(ct.net_commission), 0)
                    - COALESCE((SELECT SUM(cp.amount_paid) FROM commission_payments cp WHERE cp.entity_id = ct.entity_id AND cp.is_cancelled = 0), 0),
                COALESCE(SUM(CASE WHEN ct.status = 'pending' THEN ct.net_commission ELSE 0 END), 0),
                COALESCE(SUM(CASE WHEN ct.status = 'invoiced' THEN ct.net_commission ELSE 0 END), 0),
                COALESCE((SELECT COUNT(*) FROM commission_invoices ci WHERE ci.entity_id = ct.entity_id), 0),
                COALESCE((SELECT COUNT(*) FROM commission_payments cp WHERE cp.entity_id = ct.entity_id), 0),
                COALESCE((SELECT COUNT(*) FROM commission_credit_notes ccn WHERE ccn.invoice_id IN (SELECT ci2.id FROM commission_invoices ci2 WHERE ci2.entity_id = ct.entity_id)), 0),
                MAX(ct.order_date),
                (SELECT MAX(ci.created_at) FROM commission_invoices ci WHERE ci.entity_id = ct.entity_id),
                (SELECT MAX(cp.paid_at) FROM commission_payments cp WHERE cp.entity_id = ct.entity_id),
                NOW()
            FROM commission_transactions ct
            WHERE ct.entity_id = :entity_id
            GROUP BY ct.entity_id, ct.tenant_id
            ON DUPLICATE KEY UPDATE
                tenant_id = VALUES(tenant_id),
                total_transactions = VALUES(total_transactions),
                total_sales_count = VALUES(total_sales_count),
                total_refunds_count = VALUES(total_refunds_count),
                total_sales_amount = VALUES(total_sales_amount),
                total_refunds_amount = VALUES(total_refunds_amount),
                net_sales = VALUES(net_sales),
                total_commission = VALUES(total_commission),
                total_vat = VALUES(total_vat),
                total_net_commission = VALUES(total_net_commission),
                total_invoiced = VALUES(total_invoiced),
                total_paid = VALUES(total_paid),
                total_balance = VALUES(total_balance),
                pending_balance = VALUES(pending_balance),
                invoiced_balance = VALUES(invoiced_balance),
                total_invoices = VALUES(total_invoices),
                total_payments = VALUES(total_payments),
                total_credit_notes = VALUES(total_credit_notes),
                last_transaction_date = VALUES(last_transaction_date),
                last_invoice_date = VALUES(last_invoice_date),
                last_payment_date = VALUES(last_payment_date),
                updated_at = NOW()
        ";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':entity_id' => $entityId]);
    }
}

<?php
declare(strict_types=1);

/**
 * PDO repository for the subscriptions table.
 */
final class PdoSubscriptionsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'subscription_number', 'status', 'start_date', 'end_date', 'next_billing_date', 'created_at'];

    private const ALLOWED_COLUMNS = [
        'subscription_number', 'tenant_id', 'plan_id', 'status', 'billing_period',
        'price', 'currency_code', 'start_date', 'end_date', 'trial_end_date',
        'next_billing_date', 'auto_renew', 'cancelled_at', 'cancellation_reason',
        'suspended_at', 'suspension_reason',
    ];

    private const ALLOWED_STATUSES = ['trial', 'active', 'paused', 'cancelled', 'expired', 'suspended'];

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
        $sql = "SELECT s.* FROM subscriptions s WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY s.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM subscriptions s WHERE 1=1";
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
            $sql .= " AND s.tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$filters['tenant_id'];
        }

        if (isset($filters['plan_id']) && $filters['plan_id'] !== '') {
            $sql .= " AND s.plan_id = :plan_id";
            $params[':plan_id'] = (int)$filters['plan_id'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND s.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $sql .= " AND s.subscription_number LIKE :search";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM subscriptions WHERE id = :id LIMIT 1");
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
            INSERT INTO subscriptions
                (subscription_number, tenant_id, plan_id, status, billing_period,
                 price, currency_code, start_date, end_date, trial_end_date,
                 next_billing_date, auto_renew, created_at, updated_at)
            VALUES
                (:subscription_number, :tenant_id, :plan_id, :status, :billing_period,
                 :price, :currency_code, :start_date, :end_date, :trial_end_date,
                 :next_billing_date, :auto_renew, NOW(), NOW())
        ");

        $stmt->execute([
            ':subscription_number' => $data['subscription_number'] ?? ('SUB-' . strtoupper(bin2hex(random_bytes(5)))),
            ':tenant_id'           => (int)$data['tenant_id'],
            ':plan_id'             => (int)$data['plan_id'],
            ':status'              => $data['status'] ?? 'trial',
            ':billing_period'      => $data['billing_period'],
            ':price'               => $data['price'],
            ':currency_code'       => $data['currency_code'] ?? 'SAR',
            ':start_date'          => $data['start_date'] ?? date('Y-m-d'),
            ':end_date'            => $data['end_date'] ?? null,
            ':trial_end_date'      => $data['trial_end_date'] ?? null,
            ':next_billing_date'   => $data['next_billing_date'] ?? null,
            ':auto_renew'          => (int)($data['auto_renew'] ?? 1),
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
        $sql = "UPDATE subscriptions SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM subscriptions WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Status workflow
    // ================================
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        $extra = '';
        $params = [':id' => $id, ':status' => $status];

        if ($status === 'cancelled') {
            $extra = ", cancelled_at = NOW()";
        } elseif ($status === 'suspended') {
            $extra = ", suspended_at = NOW()";
        }

        $stmt = $this->pdo->prepare("UPDATE subscriptions SET status = :status{$extra}, updated_at = NOW() WHERE id = :id");
        return $stmt->execute($params);
    }

    // ================================
    // Stats
    // ================================
    public function stats(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'trial' THEN 1 ELSE 0 END) AS trial,
                SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) AS paused,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) AS suspended
            FROM subscriptions
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'     => (int)($row['total'] ?? 0),
            'active'    => (int)($row['active'] ?? 0),
            'trial'     => (int)($row['trial'] ?? 0),
            'paused'    => (int)($row['paused'] ?? 0),
            'cancelled' => (int)($row['cancelled'] ?? 0),
            'expired'   => (int)($row['expired'] ?? 0),
            'suspended' => (int)($row['suspended'] ?? 0),
        ];
    }
}

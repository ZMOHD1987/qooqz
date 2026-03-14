<?php
declare(strict_types=1);

/**
 * PDO repository for the subscription_plans table.
 */
final class PdoSubscriptionPlansRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'plan_name', 'code', 'plan_type', 'billing_period', 'price', 'sort_order', 'created_at'];

    private const ALLOWED_COLUMNS = [
        'plan_name', 'code', 'plan_type', 'billing_period', 'price', 'currency_code',
        'setup_fee', 'commission_rate', 'max_products', 'max_branches',
        'max_orders_per_month', 'max_staff', 'analytics_access', 'priority_support',
        'featured_listing', 'custom_domain', 'api_access', 'trial_period_days',
        'is_active', 'is_featured', 'sort_order',
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
        string $orderBy = 'sort_order',
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
        $sql = "SELECT sp.* FROM subscription_plans sp WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'sort_order';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY sp.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM subscription_plans sp WHERE 1=1";
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
        if (isset($filters['plan_type']) && $filters['plan_type'] !== '') {
            $sql .= " AND sp.plan_type = :plan_type";
            $params[':plan_type'] = $filters['plan_type'];
        }

        if (isset($filters['billing_period']) && $filters['billing_period'] !== '') {
            $sql .= " AND sp.billing_period = :billing_period";
            $params[':billing_period'] = $filters['billing_period'];
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND sp.is_active = :is_active";
            $params[':is_active'] = (int)$filters['is_active'];
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $sql .= " AND (sp.plan_name LIKE :search OR sp.code LIKE :search2)";
            $params[':search']  = '%' . trim($filters['search']) . '%';
            $params[':search2'] = '%' . trim($filters['search']) . '%';
        }
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM subscription_plans WHERE id = :id LIMIT 1");
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
            INSERT INTO subscription_plans
                (plan_name, code, plan_type, billing_period, price, currency_code,
                 setup_fee, commission_rate, max_products, max_branches,
                 max_orders_per_month, max_staff, analytics_access, priority_support,
                 featured_listing, custom_domain, api_access, trial_period_days,
                 is_active, is_featured, sort_order, created_at, updated_at)
            VALUES
                (:plan_name, :code, :plan_type, :billing_period, :price, :currency_code,
                 :setup_fee, :commission_rate, :max_products, :max_branches,
                 :max_orders_per_month, :max_staff, :analytics_access, :priority_support,
                 :featured_listing, :custom_domain, :api_access, :trial_period_days,
                 :is_active, :is_featured, :sort_order, NOW(), NOW())
        ");

        $stmt->execute([
            ':plan_name'            => $data['plan_name'],
            ':code'                 => $data['code'] ?? null,
            ':plan_type'            => $data['plan_type'],
            ':billing_period'       => $data['billing_period'],
            ':price'                => $data['price'],
            ':currency_code'        => $data['currency_code'] ?? 'SAR',
            ':setup_fee'            => $data['setup_fee'] ?? 0,
            ':commission_rate'      => $data['commission_rate'] ?? 0,
            ':max_products'         => $data['max_products'] ?? null,
            ':max_branches'         => $data['max_branches'] ?? null,
            ':max_orders_per_month' => $data['max_orders_per_month'] ?? null,
            ':max_staff'            => $data['max_staff'] ?? null,
            ':analytics_access'     => (int)($data['analytics_access'] ?? 0),
            ':priority_support'     => (int)($data['priority_support'] ?? 0),
            ':featured_listing'     => (int)($data['featured_listing'] ?? 0),
            ':custom_domain'        => (int)($data['custom_domain'] ?? 0),
            ':api_access'           => (int)($data['api_access'] ?? 0),
            ':trial_period_days'    => (int)($data['trial_period_days'] ?? 0),
            ':is_active'            => (int)($data['is_active'] ?? 1),
            ':is_featured'          => (int)($data['is_featured'] ?? 0),
            ':sort_order'           => (int)($data['sort_order'] ?? 0),
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
        $sql = "UPDATE subscription_plans SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM subscription_plans WHERE id = :id");
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
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive,
                SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) AS featured
            FROM subscription_plans
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'    => (int)($row['total'] ?? 0),
            'active'   => (int)($row['active'] ?? 0),
            'inactive' => (int)($row['inactive'] ?? 0),
            'featured' => (int)($row['featured'] ?? 0),
        ];
    }
}
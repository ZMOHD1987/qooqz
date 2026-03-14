<?php
declare(strict_types=1);

final class PdoAuctionsRepository implements AuctionsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'auctions';
    private const ALLOWED_ORDER_BY = [
        'id', 'title', 'status', 'auction_type', 'starting_price',
        'current_price', 'start_date', 'end_date', 'total_bids',
        'is_featured', 'created_at', 'updated_at'
    ];
    private const FILTERABLE_COLUMNS = [
        'status', 'auction_type', 'product_id', 'is_featured',
        'condition_type', 'currency_id'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $sql = "
            SELECT a.*,
                   COALESCE(at.title, a.title) AS translated_title,
                   at.description,
                   at.terms_conditions,
                   c.code            AS currency_code,
                   c.symbol          AS currency_symbol,
                   c.symbol_position AS currency_symbol_position,
                   c.decimal_places  AS currency_decimal_places,
                   e.store_name      AS entity_name,
                   tn.name           AS tenant_name
            FROM " . self::TABLE . " a
            LEFT JOIN auction_translations at
                ON at.auction_id = a.id AND at.language_code = :lang
            LEFT JOIN currencies c  ON c.id  = a.currency_id
            LEFT JOIN entities e    ON e.id  = a.entity_id
            LEFT JOIN tenants tn    ON tn.id = a.tenant_id
            WHERE a.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId, ':lang' => $lang];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND a.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (a.title LIKE :search OR at.title LIKE :search2)";
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY a.{$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) FROM " . self::TABLE . " WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND {$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*,
                   COALESCE(at.title, a.title) AS translated_title,
                   at.description,
                   at.terms_conditions,
                   c.code            AS currency_code,
                   c.symbol          AS currency_symbol,
                   c.symbol_position AS currency_symbol_position,
                   c.decimal_places  AS currency_decimal_places,
                   e.store_name      AS entity_name,
                   tn.name           AS tenant_name
            FROM " . self::TABLE . " a
            LEFT JOIN auction_translations at
                ON at.auction_id = a.id AND at.language_code = :lang
            LEFT JOIN currencies c  ON c.id  = a.currency_id
            LEFT JOIN entities e    ON e.id  = a.entity_id
            LEFT JOIN tenants tn    ON tn.id = a.tenant_id
            WHERE a.tenant_id = :tenant_id AND a.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        // Auto-generate slug if missing
        if (empty($data['slug'])) {
            $base = preg_replace('/[^a-z0-9\-]+/', '-', strtolower(trim($data['title'] ?? 'auction')));
            $data['slug'] = trim($base, '-') . '-' . mt_rand(1000, 9999);
        }

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    entity_id             = :entity_id,
                    product_id            = :product_id,
                    title                 = :title,
                    slug                  = :slug,
                    auction_type          = :auction_type,
                    status                = :status,
                    starting_price        = :starting_price,
                    reserve_price         = :reserve_price,
                    current_price         = :current_price,
                    buy_now_price         = :buy_now_price,
                    bid_increment         = :bid_increment,
                    currency_id           = :currency_id,
                    auto_bid_enabled      = :auto_bid_enabled,
                    start_date            = :start_date,
                    end_date              = :end_date,
                    auto_extend           = :auto_extend,
                    extend_minutes        = :extend_minutes,
                    min_extend_bid_time   = :min_extend_bid_time,
                    is_featured           = :is_featured,
                    condition_type        = :condition_type,
                    quantity              = :quantity,
                    shipping_cost         = :shipping_cost,
                    payment_deadline_hours= :payment_deadline_hours,
                    notes                 = :notes
                WHERE tenant_id = :tenant_id AND id = :id
            ");
            $stmt->execute($this->buildParams($tenantId, $data, true));
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                tenant_id, entity_id, product_id, title, slug,
                auction_type, status, starting_price, reserve_price, current_price,
                buy_now_price, bid_increment, currency_id, auto_bid_enabled,
                start_date, end_date, auto_extend, extend_minutes, min_extend_bid_time,
                is_featured, condition_type, quantity, shipping_cost,
                payment_deadline_hours, notes, created_by
            ) VALUES (
                :tenant_id, :entity_id, :product_id, :title, :slug,
                :auction_type, :status, :starting_price, :reserve_price, :current_price,
                :buy_now_price, :bid_increment, :currency_id, :auto_bid_enabled,
                :start_date, :end_date, :auto_extend, :extend_minutes, :min_extend_bid_time,
                :is_featured, :condition_type, :quantity, :shipping_cost,
                :payment_deadline_hours, :notes, :created_by
            )
        ");
        $params                = $this->buildParams($tenantId, $data, false);
        $params[':created_by'] = $data['created_by'] ?? null;
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM " . self::TABLE . " WHERE tenant_id = :tenant_id AND id = :id"
        );
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }

    private function buildParams(int $tenantId, array $data, bool $isUpdate): array
    {
        $params = [
            ':tenant_id'             => $tenantId,
            ':entity_id'             => $data['entity_id'] ?? null,
            ':product_id'            => isset($data['product_id']) ? (int)$data['product_id'] : null,
            ':title'                 => $data['title'],
            ':slug'                  => $data['slug'],
            ':auction_type'          => $data['auction_type'] ?? 'normal',
            ':status'                => $data['status'] ?? 'draft',
            ':starting_price'        => $data['starting_price'],
            ':reserve_price'         => $data['reserve_price'] ?? null,
            ':current_price'         => $data['current_price'] ?? $data['starting_price'],
            ':buy_now_price'         => $data['buy_now_price'] ?? null,
            ':bid_increment'         => $data['bid_increment'] ?? 5.00,
            ':currency_id'           => (int)($data['currency_id'] ?? 0),
            ':auto_bid_enabled'      => isset($data['auto_bid_enabled']) ? (int)$data['auto_bid_enabled'] : 1,
            ':start_date'            => $data['start_date'],
            ':end_date'              => $data['end_date'],
            ':auto_extend'           => isset($data['auto_extend']) ? (int)$data['auto_extend'] : 1,
            ':extend_minutes'        => isset($data['extend_minutes']) ? (int)$data['extend_minutes'] : 5,
            ':min_extend_bid_time'   => isset($data['min_extend_bid_time']) ? (int)$data['min_extend_bid_time'] : 5,
            ':is_featured'           => isset($data['is_featured']) ? (int)$data['is_featured'] : 0,
            ':condition_type'        => $data['condition_type'] ?? 'new',
            ':quantity'              => isset($data['quantity']) ? (int)$data['quantity'] : 1,
            ':shipping_cost'         => $data['shipping_cost'] ?? 0.00,
            ':payment_deadline_hours'=> isset($data['payment_deadline_hours']) ? (int)$data['payment_deadline_hours'] : 48,
            ':notes'                 => $data['notes'] ?? null,
        ];
        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];
        }
        return $params;
    }
}

<?php
declare(strict_types=1);

final class PdoEscrowTransactionsRepository implements EscrowTransactionsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'escrow_transactions';
    private const ALLOWED_ORDER_BY = [
        'id', 'escrow_number', 'amount', 'status', 'created_at', 'funded_at', 'released_at'
    ];
    private const FILTERABLE_COLUMNS = [
        'status', 'buyer_entity_id', 'seller_entity_id', 'order_id'
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
        string $orderDir = 'DESC'
    ): array {
        $sql = "SELECT et.*,
                    c.code            AS currency_code,
                    c.name            AS currency_name,
                    c.symbol          AS currency_symbol,
                    c.symbol_position AS currency_symbol_position,
                    c.decimal_places  AS currency_decimal_places,
                    o.order_number,
                    bet.code          AS buyer_entity_type,
                    set_.code         AS seller_entity_type,
                    be.store_name     AS buyer_store_name,
                    se.store_name     AS seller_store_name
                FROM " . self::TABLE . " et
                LEFT JOIN currencies   c    ON et.currency_id          = c.id
                LEFT JOIN entity_types bet  ON et.buyer_entity_type_id  = bet.id
                LEFT JOIN entity_types set_ ON et.seller_entity_type_id = set_.id
                LEFT JOIN orders       o    ON et.order_id              = o.id AND et.tenant_id = o.tenant_id
                LEFT JOIN entities     be   ON et.buyer_entity_id       = be.id AND et.tenant_id = be.tenant_id
                LEFT JOIN entities     se   ON et.seller_entity_id      = se.id AND et.tenant_id = se.tenant_id
                WHERE et.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND et.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['currency_code'])) {
            $sql .= " AND et.currency_id = (SELECT id FROM currencies WHERE code = :currency_code LIMIT 1)";
            $params[':currency_code'] = $filters['currency_code'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (et.escrow_number LIKE :search OR et.notes LIKE :search2)";
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY et.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM " . self::TABLE . " WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND {$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['currency_code'])) {
            $sql .= " AND currency_id = (SELECT id FROM currencies WHERE code = :currency_code LIMIT 1)";
            $params[':currency_code'] = $filters['currency_code'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (escrow_number LIKE :search OR notes LIKE :search2)";
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT et.*,
                    c.code            AS currency_code,
                    c.name            AS currency_name,
                    c.symbol          AS currency_symbol,
                    c.symbol_position AS currency_symbol_position,
                    c.decimal_places  AS currency_decimal_places,
                    o.order_number,
                    bet.code          AS buyer_entity_type,
                    set_.code         AS seller_entity_type,
                    be.store_name     AS buyer_store_name,
                    se.store_name     AS seller_store_name
             FROM " . self::TABLE . " et
             LEFT JOIN currencies   c    ON et.currency_id          = c.id
             LEFT JOIN entity_types bet  ON et.buyer_entity_type_id  = bet.id
             LEFT JOIN entity_types set_ ON et.seller_entity_type_id = set_.id
             LEFT JOIN orders       o    ON et.order_id              = o.id AND et.tenant_id = o.tenant_id
             LEFT JOIN entities     be   ON et.buyer_entity_id       = be.id AND et.tenant_id = be.tenant_id
             LEFT JOIN entities     se   ON et.seller_entity_id      = se.id AND et.tenant_id = se.tenant_id
             WHERE et.tenant_id = :tenant_id AND et.id = :id
             LIMIT 1"
        );
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        if (empty($data['escrow_number'])) {
            $data['escrow_number'] = 'ESC-' . date('Ymd') . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        $params = [
            ':buyer_entity_id'      => (int)$data['buyer_entity_id'],
            ':buyer_entity_type_id' => $this->resolveEntityTypeId($data['buyer_entity_type'] ?? null, 'buyer_entity_type'),
            ':seller_entity_id'     => (int)$data['seller_entity_id'],
            ':seller_entity_type_id'=> $this->resolveEntityTypeId($data['seller_entity_type'] ?? null, 'seller_entity_type'),
            ':amount'               => $data['amount'],
            ':currency_id'          => $this->resolveCurrencyId($data['currency_code'] ?? 'USD'),
            ':escrow_fee'           => $data['escrow_fee'] ?? 0,
            ':status'               => $data['status'] ?? 'pending',
            ':auto_release_days'    => isset($data['auto_release_days']) ? (int)$data['auto_release_days'] : 7,
            ':order_id'             => isset($data['order_id']) ? (int)$data['order_id'] : null,
            ':funded_at'            => $data['funded_at'] ?? null,
            ':shipped_at'           => $data['shipped_at'] ?? null,
            ':delivered_at'         => $data['delivered_at'] ?? null,
            ':released_at'          => $data['released_at'] ?? null,
            ':disputed_at'          => $data['disputed_at'] ?? null,
            ':resolved_at'          => $data['resolved_at'] ?? null,
            ':notes'                => $data['notes'] ?? null,
        ];

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    buyer_entity_id      = :buyer_entity_id,
                    buyer_entity_type_id = :buyer_entity_type_id,
                    seller_entity_id     = :seller_entity_id,
                    seller_entity_type_id= :seller_entity_type_id,
                    amount               = :amount,
                    currency_id          = :currency_id,
                    escrow_fee           = :escrow_fee,
                    status               = :status,
                    auto_release_days    = :auto_release_days,
                    order_id             = :order_id,
                    funded_at            = :funded_at,
                    shipped_at           = :shipped_at,
                    delivered_at         = :delivered_at,
                    released_at          = :released_at,
                    disputed_at          = :disputed_at,
                    resolved_at          = :resolved_at,
                    notes                = :notes
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            $params[':id']        = (int)$data['id'];
            $params[':tenant_id'] = $tenantId;
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                tenant_id, escrow_number, order_id,
                buyer_entity_id, buyer_entity_type_id,
                seller_entity_id, seller_entity_type_id,
                amount, currency_id, escrow_fee, status, auto_release_days,
                funded_at, shipped_at, delivered_at, released_at, disputed_at, resolved_at, notes
            ) VALUES (
                :tenant_id, :escrow_number, :order_id,
                :buyer_entity_id, :buyer_entity_type_id,
                :seller_entity_id, :seller_entity_type_id,
                :amount, :currency_id, :escrow_fee, :status, :auto_release_days,
                :funded_at, :shipped_at, :delivered_at, :released_at, :disputed_at, :resolved_at, :notes
            )
        ");
        $params[':tenant_id']     = $tenantId;
        $params[':escrow_number'] = $data['escrow_number'];
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    private function resolveEntityTypeId(?string $typeCode, string $fieldName): int
    {
        if (empty($typeCode)) {
            throw new \InvalidArgumentException("Field '{$fieldName}' is required");
        }
        $stmt = $this->pdo->prepare("SELECT id FROM entity_types WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $typeCode]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \InvalidArgumentException("Invalid {$fieldName}: '{$typeCode}'");
        }
        return (int)$id;
    }

    private function resolveCurrencyId(string $currencyCode): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM currencies WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $currencyCode]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \InvalidArgumentException("Invalid currency_code: '{$currencyCode}'");
        }
        return (int)$id;
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM " . self::TABLE . " WHERE id = :id AND tenant_id = :tenant_id"
        );
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
    }
}
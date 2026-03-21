<?php
declare(strict_types=1);

final class PdoAdCampaignsRepository implements AdCampaignsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'ad_campaigns';
    private const ALLOWED_ORDER_BY = [
        'id', 'name', 'budget', 'status', 'start_date', 'end_date', 'created_at'
    ];
    private const FILTERABLE_COLUMNS = [
        'status', 'pricing_model', 'entity_id'
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
        $sql = "SELECT ac.*,
                    c.code            AS currency_code,
                    c.name            AS currency_name,
                    c.symbol          AS currency_symbol,
                    c.symbol_position AS currency_symbol_position,
                    c.decimal_places  AS currency_decimal_places,
                    e.store_name      AS entity_store_name,
                    u.username        AS created_by_name
                FROM " . self::TABLE . " ac
                LEFT JOIN currencies c ON ac.currency_id  = c.id
                LEFT JOIN entities   e ON ac.entity_id    = e.id AND ac.tenant_id = e.tenant_id
                LEFT JOIN users      u ON ac.created_by   = u.id
                WHERE ac.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND ac.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['currency_id'])) {
            $sql .= " AND ac.currency_id = :currency_id";
            $params[':currency_id'] = (int)$filters['currency_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (ac.name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ac.{$orderBy} {$orderDir}";

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

        if (!empty($filters['currency_id'])) {
            $sql .= " AND currency_id = :currency_id";
            $params[':currency_id'] = (int)$filters['currency_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
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
            "SELECT ac.*,
                    c.code            AS currency_code,
                    c.name            AS currency_name,
                    c.symbol          AS currency_symbol,
                    c.symbol_position AS currency_symbol_position,
                    c.decimal_places  AS currency_decimal_places,
                    e.store_name      AS entity_store_name,
                    u.username        AS created_by_name
             FROM " . self::TABLE . " ac
             LEFT JOIN currencies c ON ac.currency_id  = c.id
             LEFT JOIN entities   e ON ac.entity_id    = e.id AND ac.tenant_id = e.tenant_id
             LEFT JOIN users      u ON ac.created_by   = u.id
             WHERE ac.tenant_id = :tenant_id AND ac.id = :id
             LIMIT 1"
        );
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        $params = [
            ':name'          => $data['name'],
            ':budget'        => $data['budget'] ?? 0.00,
            ':currency_id'   => (int)$data['currency_id'],
            ':pricing_model' => $data['pricing_model'] ?? 'fixed',
            ':start_date'    => $data['start_date'] ?? null,
            ':end_date'      => $data['end_date'] ?? null,
            ':status'        => $data['status'] ?? 'draft',
            ':entity_id'     => isset($data['entity_id']) ? (int)$data['entity_id'] : null,
            ':created_by'    => isset($data['created_by']) ? (int)$data['created_by'] : null,
        ];

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    name          = :name,
                    budget        = :budget,
                    currency_id   = :currency_id,
                    pricing_model = :pricing_model,
                    start_date    = :start_date,
                    end_date      = :end_date,
                    status        = :status,
                    entity_id     = :entity_id,
                    created_by    = :created_by
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            $params[':id']        = (int)$data['id'];
            $params[':tenant_id'] = $tenantId;
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                tenant_id, entity_id, name, budget, currency_id,
                pricing_model, start_date, end_date, status, created_by
            ) VALUES (
                :tenant_id, :entity_id, :name, :budget, :currency_id,
                :pricing_model, :start_date, :end_date, :status, :created_by
            )
        ");
        $params[':tenant_id'] = $tenantId;
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM " . self::TABLE . " WHERE id = :id AND tenant_id = :tenant_id"
        );
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
    }
}
<?php
declare(strict_types=1);

final class PdoAdPaymentsRepository implements AdPaymentsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'ad_payments';
    private const ALLOWED_ORDER_BY = ['id', 'amount', 'status', 'paid_at', 'created_at'];
    private const FILTERABLE_COLUMNS = ['campaign_id', 'status'];

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
        $sql = "SELECT ap.*,
                    c.code            AS currency_code,
                    c.name            AS currency_name,
                    c.symbol          AS currency_symbol,
                    c.symbol_position AS currency_symbol_position,
                    c.decimal_places  AS currency_decimal_places,
                    ac.name           AS campaign_name,
                    ac.tenant_id      AS campaign_tenant_id
                FROM " . self::TABLE . " ap
                LEFT JOIN currencies   c  ON ap.currency_id  = c.id
                INNER JOIN ad_campaigns ac ON ap.campaign_id  = ac.id
                WHERE ac.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND ap.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['currency_id'])) {
            $sql .= " AND ap.currency_id = :currency_id";
            $params[':currency_id'] = (int)$filters['currency_id'];
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ap.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM " . self::TABLE . " ap
                INNER JOIN ad_campaigns ac ON ap.campaign_id = ac.id
                WHERE ac.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND ap.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['currency_id'])) {
            $sql .= " AND ap.currency_id = :currency_id";
            $params[':currency_id'] = (int)$filters['currency_id'];
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
            "SELECT ap.*,
                    c.code            AS currency_code,
                    c.name            AS currency_name,
                    c.symbol          AS currency_symbol,
                    c.symbol_position AS currency_symbol_position,
                    c.decimal_places  AS currency_decimal_places,
                    ac.name           AS campaign_name,
                    ac.tenant_id      AS campaign_tenant_id
             FROM " . self::TABLE . " ap
             LEFT JOIN currencies   c  ON ap.currency_id  = c.id
             INNER JOIN ad_campaigns ac ON ap.campaign_id  = ac.id
             WHERE ac.tenant_id = :tenant_id AND ap.id = :id
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
            ':campaign_id' => (int)$data['campaign_id'],
            ':amount'      => $data['amount'] ?? null,
            ':currency_id' => (int)$data['currency_id'],
            ':status'      => $data['status'] ?? 'pending',
            ':paid_at'     => $data['paid_at'] ?? null,
        ];

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    campaign_id = :campaign_id,
                    amount      = :amount,
                    currency_id = :currency_id,
                    status      = :status,
                    paid_at     = :paid_at
                WHERE id = :id
            ");
            $params[':id'] = (int)$data['id'];
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                campaign_id, amount, currency_id, status, paid_at
            ) VALUES (
                :campaign_id, :amount, :currency_id, :status, :paid_at
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE ap FROM " . self::TABLE . " ap
             INNER JOIN ad_campaigns ac ON ap.campaign_id = ac.id
             WHERE ap.id = :id AND ac.tenant_id = :tenant_id"
        );
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
    }
}

<?php
declare(strict_types=1);

final class PdoAdsRepository implements AdsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'ads';
    private const ALLOWED_ORDER_BY = [
        'id', 'campaign_id', 'target_type', 'status', 'created_at'
    ];
    private const FILTERABLE_COLUMNS = ['status', 'target_type'];

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
        $sql = "SELECT a.*,
                    ac.name      AS campaign_name,
                    ac.status    AS campaign_status,
                    ac.tenant_id AS campaign_tenant_id,
                    COALESCE(SUM(s.views),  0) AS views_total,
                    COALESCE(SUM(s.clicks), 0) AS clicks_total
                FROM " . self::TABLE . " a
                INNER JOIN ad_campaigns ac ON a.campaign_id = ac.id
                LEFT JOIN ad_stats s ON s.ad_id = a.id
                WHERE ac.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND a.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['campaign_id'])) {
            $sql .= " AND a.campaign_id = :campaign_id";
            $params[':campaign_id'] = (int)$filters['campaign_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (a.target_value LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " GROUP BY a.id";

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
        $sql = "SELECT COUNT(DISTINCT a.id) FROM " . self::TABLE . " a
                INNER JOIN ad_campaigns ac ON a.campaign_id = ac.id
                WHERE ac.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND a.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['campaign_id'])) {
            $sql .= " AND a.campaign_id = :campaign_id";
            $params[':campaign_id'] = (int)$filters['campaign_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (a.target_value LIKE :search)";
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
            "SELECT a.*,
                    ac.name      AS campaign_name,
                    ac.status    AS campaign_status,
                    ac.tenant_id AS campaign_tenant_id,
                    COALESCE(SUM(s.views),  0) AS views_total,
                    COALESCE(SUM(s.clicks), 0) AS clicks_total
             FROM " . self::TABLE . " a
             INNER JOIN ad_campaigns ac ON a.campaign_id = ac.id
             LEFT JOIN ad_stats s ON s.ad_id = a.id
             WHERE ac.tenant_id = :tenant_id AND a.id = :id
             GROUP BY a.id
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
            ':campaign_id'   => (int)$data['campaign_id'],
            ':target_type'   => $data['target_type']  ?? 'url',
            ':target_value'  => $data['target_value'] ?? null,
            ':status'        => $data['status']        ?? 'active',
        ];

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " a
                INNER JOIN ad_campaigns ac ON a.campaign_id = ac.id
                SET
                    a.campaign_id  = :campaign_id,
                    a.target_type  = :target_type,
                    a.target_value = :target_value,
                    a.status       = :status
                WHERE a.id = :id AND ac.tenant_id = :tenant_id
            ");
            $params[':id']        = (int)$data['id'];
            $params[':tenant_id'] = $tenantId;
            $stmt->execute($params);
            return (int)$data['id'];
        }

        // Verify campaign belongs to the tenant before inserting
        $checkStmt = $this->pdo->prepare(
            "SELECT id FROM ad_campaigns WHERE id = :campaign_id AND tenant_id = :tenant_id LIMIT 1"
        );
        $checkStmt->execute([':campaign_id' => $params[':campaign_id'], ':tenant_id' => $tenantId]);
        if (!$checkStmt->fetch()) {
            throw new \RuntimeException('Campaign not found or does not belong to this tenant');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                campaign_id, target_type, target_value, status
            ) VALUES (
                :campaign_id, :target_type, :target_value, :status
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE a FROM " . self::TABLE . " a
             INNER JOIN ad_campaigns ac ON a.campaign_id = ac.id
             WHERE a.id = :id AND ac.tenant_id = :tenant_id"
        );
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
    }
}

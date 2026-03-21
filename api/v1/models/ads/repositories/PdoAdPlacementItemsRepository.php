<?php
declare(strict_types=1);

final class PdoAdPlacementItemsRepository implements AdPlacementItemsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'ad_placement_items';
    private const ALLOWED_ORDER_BY = ['id', 'placement_id', 'ad_id', 'priority', 'weight', 'start_date', 'end_date', 'created_at'];
    private const FILTERABLE_COLUMNS = ['placement_id', 'ad_id'];
    private const DEFAULT_LANG = 'en';

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
        $sql = "SELECT api.*,
                    COALESCE(atr.title, '') AS ad_title
                FROM " . self::TABLE . " api
                INNER JOIN ad_placements ap ON api.placement_id = ap.id
                LEFT JOIN ads a ON api.ad_id = a.id
                LEFT JOIN ad_translations atr ON a.id = atr.ad_id AND atr.language_code = :lang
                WHERE ap.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId, ':lang' => self::DEFAULT_LANG];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND api.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY api.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM " . self::TABLE . " api
                INNER JOIN ad_placements ap ON api.placement_id = ap.id
                WHERE ap.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND api.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
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
            "SELECT api.*,
                    COALESCE(atr.title, '') AS ad_title
             FROM " . self::TABLE . " api
             INNER JOIN ad_placements ap ON api.placement_id = ap.id
             LEFT JOIN ads a ON api.ad_id = a.id
             LEFT JOIN ad_translations atr ON a.id = atr.ad_id AND atr.language_code = :lang
             WHERE ap.tenant_id = :tenant_id AND api.id = :id
             LIMIT 1"
        );
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id, ':lang' => self::DEFAULT_LANG]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        $params = [
            ':placement_id' => (int)$data['placement_id'],
            ':ad_id'        => (int)$data['ad_id'],
            ':priority'     => isset($data['priority']) ? (int)$data['priority'] : 1,
            ':weight'       => isset($data['weight']) ? (int)$data['weight'] : 1,
            ':start_date'   => $data['start_date'] ?? null,
            ':end_date'     => $data['end_date'] ?? null,
        ];

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    placement_id = :placement_id,
                    ad_id        = :ad_id,
                    priority     = :priority,
                    weight       = :weight,
                    start_date   = :start_date,
                    end_date     = :end_date
                WHERE id = :id
            ");
            $params[':id'] = (int)$data['id'];
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                placement_id, ad_id, priority, weight, start_date, end_date
            ) VALUES (
                :placement_id, :ad_id, :priority, :weight, :start_date, :end_date
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE api FROM " . self::TABLE . " api
             INNER JOIN ad_placements ap ON api.placement_id = ap.id
             WHERE api.id = :id AND ap.tenant_id = :tenant_id"
        );
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
    }
}
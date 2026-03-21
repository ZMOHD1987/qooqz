<?php
declare(strict_types=1);

final class PdoAdTranslationsRepository implements AdTranslationsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'ad_translations';
    private const ALLOWED_ORDER_BY = ['id', 'ad_id', 'language_code', 'created_at'];
    private const FILTERABLE_COLUMNS = ['ad_id', 'language_code'];

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
        $sql = "SELECT atr.*,
                    ac.name      AS campaign_name,
                    ac.tenant_id AS campaign_tenant_id
                FROM " . self::TABLE . " atr
                INNER JOIN ad_campaigns ac ON atr.ad_id = ac.id
                WHERE ac.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND atr.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (atr.title LIKE :search OR atr.description LIKE :search2)";
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY atr.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM " . self::TABLE . " atr
                INNER JOIN ad_campaigns ac ON atr.ad_id = ac.id
                WHERE ac.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND atr.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (atr.title LIKE :search OR atr.description LIKE :search2)";
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
            "SELECT atr.*,
                    ac.name      AS campaign_name,
                    ac.tenant_id AS campaign_tenant_id
             FROM " . self::TABLE . " atr
             INNER JOIN ad_campaigns ac ON atr.ad_id = ac.id
             WHERE ac.tenant_id = :tenant_id AND atr.id = :id
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
            ':ad_id'         => (int)$data['ad_id'],
            ':language_code' => $data['language_code'],
            ':title'         => $data['title'] ?? null,
            ':description'   => $data['description'] ?? null,
        ];

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    ad_id         = :ad_id,
                    language_code = :language_code,
                    title         = :title,
                    description   = :description
                WHERE id = :id
            ");
            $params[':id'] = (int)$data['id'];
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                ad_id, language_code, title, description
            ) VALUES (
                :ad_id, :language_code, :title, :description
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE atr FROM " . self::TABLE . " atr
             INNER JOIN ad_campaigns ac ON atr.ad_id = ac.id
             WHERE atr.id = :id AND ac.tenant_id = :tenant_id"
        );
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
    }
}

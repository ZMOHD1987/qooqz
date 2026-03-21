<?php
declare(strict_types=1);

final class PdoAdPlacementsRepository implements AdPlacementsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'ad_placements';
    private const ALLOWED_ORDER_BY = ['id', 'name', 'placement_key', 'status', 'created_at', 'updated_at'];
    private const FILTERABLE_COLUMNS = ['status'];

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
        $sql    = "SELECT * FROM " . self::TABLE . " WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '' && $filters[$col] !== null) {
                $sql .= " AND {$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE :search OR placement_key LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit',  $limit,        PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0,  PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) FROM " . self::TABLE . " WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '' && $filters[$col] !== null) {
                $sql .= " AND {$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE :search OR placement_key LIKE :search)";
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
            "SELECT * FROM " . self::TABLE . " WHERE tenant_id = :tenant_id AND id = :id LIMIT 1"
        );
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        $params = [
            ':tenant_id'     => $tenantId,
            ':name'          => trim((string)($data['name'] ?? '')),
            ':code'          => isset($data['code']) && trim((string)$data['code']) !== '' ? trim((string)$data['code']) : null,
            ':placement_key' => trim((string)($data['placement_key'] ?? '')),
            ':description'   => isset($data['description']) && (string)$data['description'] !== '' ? (string)$data['description'] : null,
            ':page'          => isset($data['page']) && trim((string)$data['page']) !== '' ? trim((string)$data['page']) : null,
            ':width'         => isset($data['width']) && $data['width'] !== '' && $data['width'] !== null ? (int)$data['width'] : null,
            ':height'        => isset($data['height']) && $data['height'] !== '' && $data['height'] !== null ? (int)$data['height'] : null,
            ':max_ads'       => isset($data['max_ads']) && $data['max_ads'] !== '' && $data['max_ads'] !== null ? (int)$data['max_ads'] : 1,
            ':status'        => $data['status'] ?? 'active',
        ];

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    name          = :name,
                    code          = :code,
                    placement_key = :placement_key,
                    description   = :description,
                    page          = :page,
                    width         = :width,
                    height        = :height,
                    max_ads       = :max_ads,
                    status        = :status
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            $params[':id'] = (int)$data['id'];
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . "
                (tenant_id, name, code, placement_key, description, page, width, height, max_ads, status)
            VALUES
                (:tenant_id, :name, :code, :placement_key, :description, :page, :width, :height, :max_ads, :status)
        ");
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
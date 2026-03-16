<?php
declare(strict_types=1);

final class PdoReturnStatusHistoryRepository implements ReturnStatusHistoryRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'return_status_history';
    private const ALLOWED_ORDER_BY = ['id', 'return_id', 'status', 'created_at'];
    private const FILTERABLE_COLUMNS = ['return_id', 'status', 'changed_by'];

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
        // نربط الجدول بـ returns للتأكد من tenant_id
        $sql = "
            SELECT rsh.*,
                   r.return_number,
                   u.email AS changed_by_email
            FROM " . self::TABLE . " rsh
            INNER JOIN returns r  ON r.id  = rsh.return_id
            LEFT JOIN users u     ON u.id  = rsh.changed_by
            WHERE r.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND rsh.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY rsh.{$orderBy} {$orderDir}";

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
        $sql = "
            SELECT COUNT(*) 
            FROM " . self::TABLE . " rsh
            INNER JOIN returns r ON r.id = rsh.return_id
            WHERE r.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND rsh.{$col} = :{$col}";
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
            SELECT rsh.*,
                   r.return_number,
                   u.email AS changed_by_email
            FROM " . self::TABLE . " rsh
            INNER JOIN returns r  ON r.id  = rsh.return_id
            LEFT JOIN users u     ON u.id  = rsh.changed_by
            WHERE r.tenant_id = :tenant_id AND rsh.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        // التحقق من ملكية return_id للمستأجر
        if (!$this->validateReturnOwnership($tenantId, (int)$data['return_id'])) {
            throw new RuntimeException('Return request does not belong to tenant');
        }

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    return_id   = :return_id,
                    status      = :status,
                    changed_by  = :changed_by,
                    notes       = :notes
                WHERE id = :id
            ");
            $stmt->execute($this->buildParams($data, true));
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                return_id, status, changed_by, notes
            ) VALUES (
                :return_id, :status, :changed_by, :notes
            )
        ");
        $stmt->execute($this->buildParams($data, false));
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE rsh FROM " . self::TABLE . " rsh
            INNER JOIN returns r ON r.id = rsh.return_id
            WHERE rsh.id = :id AND r.tenant_id = :tenant_id
        ");
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
    }

    private function validateReturnOwnership(int $tenantId, int $returnId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM returns WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute([':id' => $returnId, ':tenant_id' => $tenantId]);
        return (bool)$stmt->fetchColumn();
    }

    private function buildParams(array $data, bool $isUpdate): array
    {
        $params = [
            ':return_id'  => (int)$data['return_id'],
            ':status'     => $data['status'],
            ':changed_by' => isset($data['changed_by']) ? (int)$data['changed_by'] : null,
            ':notes'      => $data['notes'] ?? null,
        ];
        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];
        }
        return $params;
    }
}
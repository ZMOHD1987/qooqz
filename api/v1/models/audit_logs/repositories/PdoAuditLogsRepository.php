<?php
declare(strict_types=1);

final class PdoAuditLogsRepository implements AuditLogsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'audit_logs';
    private const ALLOWED_ORDER_BY = ['id', 'action', 'entity_type', 'created_at'];
    private const FILTERABLE_COLUMNS = ['action', 'entity_type', 'entity_id', 'user_id'];

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
        $sql = "
            SELECT al.*, u.email AS user_email
            FROM " . self::TABLE . " al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND al.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        // البحث في الـ Action أو Entity Type
        if (!empty($filters['search'])) {
            $sql .= " AND (al.action LIKE :search OR al.entity_type LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY al.{$orderBy} {$orderDir}";

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

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT al.*, u.email AS user_email
            FROM " . self::TABLE . " al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.tenant_id = :tenant_id AND al.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(array $data): int
    {
        // ملاحظة: الجدول لا يحتوي على created_at في الوصف، لكن نعتمد على id للتسلسل
        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                tenant_id, entity_type, entity_id, user_id, action, 
                ip_address, user_agent, payload
            ) VALUES (
                :tenant_id, :entity_type, :entity_id, :user_id, :action,
                :ip_address, :user_agent, :payload
            )
        ");

        $stmt->execute([
            ':tenant_id'    => $data['tenant_id'] ?? null,
            ':entity_type'  => $data['entity_type'] ?? null,
            ':entity_id'    => $data['entity_id'] ?? null,
            ':user_id'      => $data['user_id'] ?? null,
            ':action'       => $data['action'],
            ':ip_address'   => $data['ip_address'] ?? null,
            ':user_agent'   => $data['user_agent'] ?? null,
            ':payload'      => isset($data['payload']) ? json_encode($data['payload']) : null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }
}
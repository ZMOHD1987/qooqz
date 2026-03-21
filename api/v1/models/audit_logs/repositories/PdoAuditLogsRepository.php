<?php
declare(strict_types=1);

final class PdoAuditLogsRepository implements AuditLogsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'audit_logs';
    private const ALLOWED_ORDER_BY = ['id', 'action', 'entity_type', 'created_at', 'duration_ms'];
    private const FILTERABLE_COLUMNS = ['action', 'entity_type', 'entity_id', 'user_id', 'http_method', 'session_id', 'request_id'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────

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

        if (!empty($filters['search'])) {
            $sql .= " AND (al.action LIKE :search OR al.entity_type LIKE :search OR al.http_url LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND al.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND al.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
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

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'decodeJsonColumns'], $rows);
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

        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
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
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decodeJsonColumns($row) : null;
    }

    // ─────────────────────────────────────────────────────────────
    // WRITE
    // ─────────────────────────────────────────────────────────────

    public function save(array $data): int
    {
        // Auto-compute diff when old_values and new_values are provided but diff is not
        if (!isset($data['diff']) && isset($data['old_values']) && isset($data['new_values'])) {
            $data['diff'] = self::computeDiff(
                (array)$data['old_values'],
                (array)$data['new_values']
            );
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                tenant_id, entity_type, entity_id, user_id, action,
                ip_address, user_agent,
                payload, old_values, new_values, diff,
                metadata, trace,
                http_method, http_url, session_id, request_id, duration_ms
            ) VALUES (
                :tenant_id, :entity_type, :entity_id, :user_id, :action,
                :ip_address, :user_agent,
                :payload, :old_values, :new_values, :diff,
                :metadata, :trace,
                :http_method, :http_url, :session_id, :request_id, :duration_ms
            )
        ");

        $stmt->execute([
            ':tenant_id'   => $data['tenant_id']   ?? null,
            ':entity_type' => $data['entity_type'] ?? null,
            ':entity_id'   => $data['entity_id']   ?? null,
            ':user_id'     => $data['user_id']      ?? null,
            ':action'      => $data['action'],
            ':ip_address'  => $data['ip_address']  ?? null,
            ':user_agent'  => $data['user_agent']   ?? null,
            ':payload'     => isset($data['payload'])    ? json_encode($data['payload'],    JSON_UNESCAPED_UNICODE) : null,
            ':old_values'  => isset($data['old_values']) ? json_encode($data['old_values'], JSON_UNESCAPED_UNICODE) : null,
            ':new_values'  => isset($data['new_values']) ? json_encode($data['new_values'], JSON_UNESCAPED_UNICODE) : null,
            ':diff'        => isset($data['diff'])       ? json_encode($data['diff'],       JSON_UNESCAPED_UNICODE) : null,
            ':metadata'    => isset($data['metadata'])   ? json_encode($data['metadata'],   JSON_UNESCAPED_UNICODE) : null,
            ':trace'       => $data['trace']       ?? null,
            ':http_method' => $data['http_method'] ?? null,
            ':http_url'    => $data['http_url']    ?? null,
            ':session_id'  => $data['session_id']  ?? null,
            ':request_id'  => $data['request_id']  ?? null,
            ':duration_ms' => isset($data['duration_ms']) ? (int)$data['duration_ms'] : null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Compute a field-level diff between two flat associative arrays.
     * Returns an array of ['field' => ..., 'old' => ..., 'new' => ...] entries.
     */
    public static function computeDiff(array $old, array $new): array
    {
        $diff = [];
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;
            // Loose comparison to catch int/string mismatches from JSON decode
            if ((string)$oldVal !== (string)$newVal) {
                $diff[] = ['field' => $key, 'old' => $oldVal, 'new' => $newVal];
            }
        }
        return $diff;
    }

    /**
     * Decode JSON columns from a DB row so callers receive native PHP arrays.
     */
    private function decodeJsonColumns(array $row): array
    {
        foreach (['payload', 'old_values', 'new_values', 'diff', 'metadata'] as $col) {
            if (isset($row[$col]) && is_string($row[$col])) {
                $decoded = json_decode($row[$col], true);
                $row[$col] = ($decoded !== null) ? $decoded : $row[$col];
            }
        }
        return $row;
    }
}
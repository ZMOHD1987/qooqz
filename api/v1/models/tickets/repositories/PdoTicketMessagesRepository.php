<?php
declare(strict_types=1);

final class PdoTicketMessagesRepository implements TicketMessagesRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'ticket_messages';
    private const ALLOWED_ORDER_BY = ['id', 'created_at'];
    private const FILTERABLE_COLUMNS = ['ticket_id', 'sender_user_id', 'is_internal'];

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
        $sql = "
            SELECT m.*, 
                   u.email AS sender_email
            FROM " . self::TABLE . " m
            LEFT JOIN users u ON m.sender_user_id = u.id
            WHERE m.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND m.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        // Default ordering for messages is usually chronological
        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY m.{$orderBy} {$orderDir}";

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

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*, 
                   u.email AS sender_email
            FROM " . self::TABLE . " m
            LEFT JOIN users u ON m.sender_user_id = u.id
            WHERE m.tenant_id = :tenant_id AND m.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        $params = [
            ':ticket_id'      => (int)$data['ticket_id'],
            ':sender_user_id' => (int)$data['sender_user_id'],
            ':message'        => $data['message'],
            ':is_internal'    => isset($data['is_internal']) ? (int)$data['is_internal'] : 0,
            ':attachments'    => isset($data['attachments']) ? json_encode($data['attachments']) : null,
        ];

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    ticket_id      = :ticket_id,
                    sender_user_id = :sender_user_id,
                    message        = :message,
                    is_internal    = :is_internal,
                    attachments    = :attachments
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            $params[':id'] = (int)$data['id'];
            $params[':tenant_id'] = $tenantId;
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                tenant_id, ticket_id, sender_user_id, message, is_internal, attachments
            ) VALUES (
                :tenant_id, :ticket_id, :sender_user_id, :message, :is_internal, :attachments
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
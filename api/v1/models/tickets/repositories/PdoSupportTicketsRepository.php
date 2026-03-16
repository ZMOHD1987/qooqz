<?php
declare(strict_types=1);

final class PdoSupportTicketsRepository implements SupportTicketsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'support_tickets';
    private const ALLOWED_ORDER_BY = [
        'id', 'ticket_number', 'subject', 'status', 'priority', 'created_at'
    ];
    private const FILTERABLE_COLUMNS = [
        'status', 'priority', 'user_id', 'category_id', 'assigned_to'
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
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $sql = "
            SELECT t.*,
                   ct.name          AS category_name,
                   u.email          AS user_email,
                   e.store_name     AS entity_name,
                   ua.email         AS assigned_to_email
            FROM " . self::TABLE . " t
            LEFT JOIN ticket_categories c  ON t.category_id   = c.id
            LEFT JOIN ticket_category_translations ct
                ON c.id = ct.category_id AND ct.language_code = :lang
            LEFT JOIN users u              ON t.user_id       = u.id
            LEFT JOIN entities e           ON t.entity_id     = e.id
            LEFT JOIN users ua             ON t.assigned_to   = ua.id
            WHERE t.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId, ':lang' => $lang];

        // Handle Filters
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND t.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (t.subject LIKE :search OR t.ticket_number LIKE :search2)";
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY t.{$orderBy} {$orderDir}";

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

        if (!empty($filters['search'])) {
            $sql .= " AND (subject LIKE :search OR ticket_number LIKE :search2)";
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*,
                   ct.name          AS category_name,
                   u.email          AS user_email,
                   e.store_name     AS entity_name,
                   ua.email         AS assigned_to_email
            FROM " . self::TABLE . " t
            LEFT JOIN ticket_categories c  ON t.category_id   = c.id
            LEFT JOIN ticket_category_translations ct
                ON c.id = ct.category_id AND ct.language_code = :lang
            LEFT JOIN users u              ON t.user_id       = u.id
            LEFT JOIN entities e           ON t.entity_id     = e.id
            LEFT JOIN users ua             ON t.assigned_to   = ua.id
            WHERE t.tenant_id = :tenant_id AND t.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        // Auto-generate ticket number if missing
        if (empty($data['ticket_number'])) {
            $data['ticket_number'] = 'TKT-' . date('Ymd') . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        $params = [
            ':user_id'      => (int)$data['user_id'],
            ':entity_id'    => isset($data['entity_id']) ? (int)$data['entity_id'] : null,
            ':order_id'     => isset($data['order_id']) ? (int)$data['order_id'] : null,
            ':category_id'  => (int)$data['category_id'],
            ':subject'      => $data['subject'],
            ':description'  => $data['description'],
            ':priority'     => $data['priority'] ?? 'normal',
            ':status'       => $data['status'] ?? 'open',
            ':assigned_to'  => isset($data['assigned_to']) ? (int)$data['assigned_to'] : null,
            ':attachments'  => isset($data['attachments']) ? json_encode($data['attachments']) : null,
        ];

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    user_id      = :user_id,
                    entity_id    = :entity_id,
                    order_id     = :order_id,
                    category_id  = :category_id,
                    subject      = :subject,
                    description  = :description,
                    priority     = :priority,
                    status       = :status,
                    assigned_to  = :assigned_to,
                    attachments  = :attachments
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            $params[':id'] = (int)$data['id'];
            $params[':tenant_id'] = $tenantId;
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                tenant_id, ticket_number, user_id, entity_id, order_id, category_id,
                subject, description, priority, status, assigned_to, attachments
            ) VALUES (
                :tenant_id, :ticket_number, :user_id, :entity_id, :order_id, :category_id,
                :subject, :description, :priority, :status, :assigned_to, :attachments
            )
        ");
        $params[':tenant_id'] = $tenantId;
        $params[':ticket_number'] = $data['ticket_number'];
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
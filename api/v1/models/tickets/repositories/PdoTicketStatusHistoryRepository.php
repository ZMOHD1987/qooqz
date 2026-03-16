<?php
declare(strict_types=1);

final class PdoTicketStatusHistoryRepository implements TicketStatusHistoryRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'ticket_status_history';
    private const ALLOWED_ORDER_BY = ['id', 'created_at', 'new_status'];
    private const FILTERABLE_COLUMNS = ['ticket_id', 'new_status', 'changed_by'];

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
        // نحتاج للربط مع جدول support_tickets للتحقق من tenant_id
        // لأن جدول التاريخ لا يحتوي على tenant_id مباشرة
        $sql = "
            SELECT h.*,
                   u.email AS changed_by_email
            FROM " . self::TABLE . " h
            INNER JOIN support_tickets t ON h.ticket_id = t.id
            LEFT JOIN users u ON h.changed_by = u.id
            WHERE t.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND h.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY h.{$orderBy} {$orderDir}";

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
            FROM " . self::TABLE . " h
            INNER JOIN support_tickets t ON h.ticket_id = t.id
            WHERE t.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND h.{$col} = :{$col}";
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
            SELECT h.*,
                   u.email AS changed_by_email
            FROM " . self::TABLE . " h
            INNER JOIN support_tickets t ON h.ticket_id = t.id
            LEFT JOIN users u ON h.changed_by = u.id
            WHERE t.tenant_id = :tenant_id AND h.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        // التحقق من أن التذكرة تابعة للمستأجر قبل الحفظ
        if (!$this->validateTicketOwnership($tenantId, (int)$data['ticket_id'])) {
            throw new RuntimeException('Ticket does not belong to tenant');
        }

        $params = [
            ':ticket_id'   => (int)$data['ticket_id'],
            ':old_status'  => $data['old_status'] ?? null,
            ':new_status'  => $data['new_status'],
            ':changed_by'  => isset($data['changed_by']) ? (int)$data['changed_by'] : null,
            ':notes'       => $data['notes'] ?? null,
        ];

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    ticket_id   = :ticket_id,
                    old_status  = :old_status,
                    new_status  = :new_status,
                    changed_by  = :changed_by,
                    notes       = :notes
                WHERE id = :id
            ");
            $params[':id'] = (int)$data['id'];
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                ticket_id, old_status, new_status, changed_by, notes
            ) VALUES (
                :ticket_id, :old_status, :new_status, :changed_by, :notes
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        // الحذف مع التحقق من الملكية عبر JOIN
        $stmt = $this->pdo->prepare("
            DELETE h FROM " . self::TABLE . " h
            INNER JOIN support_tickets t ON h.ticket_id = t.id
            WHERE h.id = :id AND t.tenant_id = :tenant_id
        ");
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
    }

    private function validateTicketOwnership(int $tenantId, int $ticketId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM support_tickets WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute([':id' => $ticketId, ':tenant_id' => $tenantId]);
        return (bool)$stmt->fetchColumn();
    }
}
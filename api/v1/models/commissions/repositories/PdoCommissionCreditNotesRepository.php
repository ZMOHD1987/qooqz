<?php
declare(strict_types=1);

/**
 * PDO repository for the commission_credit_notes table.
 */
final class PdoCommissionCreditNotesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'credit_note_number', 'credit_amount', 'net_credit', 'status', 'created_at'];

    private const ALLOWED_COLUMNS = [
        'tenant_id', 'credit_note_number', 'invoice_id', 'related_transaction_id',
        'credit_amount', 'credit_commission', 'credit_vat', 'net_credit',
        'reason', 'status', 'issued_at',
        'created_by', 'issued_by', 'cancelled_by',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with filters, ordering, pagination
    // ================================
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'created_at',
        string $orderDir = 'DESC'
    ): array {
        $items = $this->query($limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->count($filters);

        return [
            'items' => $items,
            'meta'  => [
                'total'       => $total,
                'limit'       => $limit,
                'offset'      => $offset,
                'total_pages' => ($limit !== null && $limit > 0) ? (int)ceil($total / $limit) : 0,
            ],
        ];
    }

    // ================================
    // Query rows
    // ================================
    private function query(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        $sql = "SELECT ccn.* FROM commission_credit_notes ccn WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'created_at';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY ccn.{$orderBy} {$orderDir}";

        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        if ($limit  !== null) $stmt->bindValue(':limit',  (int)$limit,  PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Count
    // ================================
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM commission_credit_notes ccn WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Apply filters
    // ================================
    private function applyFilters(string &$sql, array &$params, array $filters): void
    {
        if (isset($filters['tenant_id']) && $filters['tenant_id'] !== '') {
            $sql .= " AND ccn.tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$filters['tenant_id'];
        }

        if (isset($filters['invoice_id']) && $filters['invoice_id'] !== '') {
            $sql .= " AND ccn.invoice_id = :invoice_id";
            $params[':invoice_id'] = (int)$filters['invoice_id'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND ccn.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (isset($filters['date_from']) && $filters['date_from'] !== '') {
            $sql .= " AND ccn.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to']) && $filters['date_to'] !== '') {
            $sql .= " AND ccn.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM commission_credit_notes WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    // ================================
    // Create
    // ================================
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO commission_credit_notes
                (tenant_id, credit_note_number, invoice_id, related_transaction_id,
                 credit_amount, credit_commission, credit_vat, net_credit,
                 reason, status, issued_at,
                 created_by, issued_by, cancelled_by, created_at)
            VALUES
                (:tenant_id, :credit_note_number, :invoice_id, :related_transaction_id,
                 :credit_amount, :credit_commission, :credit_vat, :net_credit,
                 :reason, :status, :issued_at,
                 :created_by, :issued_by, :cancelled_by, NOW())
        ");

        $stmt->execute([
            ':tenant_id'              => (int)$data['tenant_id'],
            ':credit_note_number'     => $data['credit_note_number'] ?? $this->generateCreditNoteNumber(),
            ':invoice_id'             => (int)$data['invoice_id'],
            ':related_transaction_id' => (int)$data['related_transaction_id'],
            ':credit_amount'          => $data['credit_amount'],
            ':credit_commission'      => $data['credit_commission'],
            ':credit_vat'             => $data['credit_vat'],
            ':net_credit'             => $data['net_credit'],
            ':reason'                 => $data['reason'] ?? null,
            ':status'                 => $data['status'] ?? 'draft',
            ':issued_at'              => $data['issued_at'] ?? null,
            ':created_by'             => isset($data['created_by']) ? (int)$data['created_by'] : null,
            ':issued_by'              => isset($data['issued_by']) ? (int)$data['issued_by'] : null,
            ':cancelled_by'           => isset($data['cancelled_by']) ? (int)$data['cancelled_by'] : null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Update
    // ================================
    public function update(int $id, array $data): bool
    {
        $setClauses = [];
        $params = [':id' => $id];

        foreach (self::ALLOWED_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $setClauses[] = "{$col} = :{$col}";
                $params[':' . $col] = $data[$col];
            }
        }

        if (empty($setClauses)) {
            throw new InvalidArgumentException("No valid fields provided for update");
        }

        $sql = "UPDATE commission_credit_notes SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM commission_credit_notes WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Stats
    // ================================
    public function stats(?int $tenantId = null): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft,
                SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) AS issued,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                COALESCE(SUM(credit_amount), 0) AS total_credit_amount,
                COALESCE(SUM(net_credit), 0) AS total_net_credit
            FROM commission_credit_notes
            WHERE 1=1
        ";
        $params = [];

        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'               => (int)($row['total'] ?? 0),
            'draft'               => (int)($row['draft'] ?? 0),
            'issued'              => (int)($row['issued'] ?? 0),
            'cancelled'           => (int)($row['cancelled'] ?? 0),
            'total_credit_amount' => (float)($row['total_credit_amount'] ?? 0),
            'total_net_credit'    => (float)($row['total_net_credit'] ?? 0),
        ];
    }

    // ================================
    // Generate credit note number: CCN-YYYYMMDD-XXXXX
    // ================================
    public function generateCreditNoteNumber(): string
    {
        $datePrefix = 'CCN-' . date('Ymd') . '-';

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                SELECT credit_note_number FROM commission_credit_notes
                WHERE credit_note_number LIKE :prefix
                ORDER BY credit_note_number DESC
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':prefix' => $datePrefix . '%']);
            $last = $stmt->fetchColumn();

            if ($last) {
                $lastSeq = (int)substr($last, -5);
                $nextSeq = $lastSeq + 1;
            } else {
                $nextSeq = 1;
            }

            $number = $datePrefix . str_pad((string)$nextSeq, 5, '0', STR_PAD_LEFT);
            $this->pdo->commit();

            return $number;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

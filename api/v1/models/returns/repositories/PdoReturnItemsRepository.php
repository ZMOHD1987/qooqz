<?php
declare(strict_types=1);

final class PdoReturnItemsRepository implements ReturnItemsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'return_items';
    private const ALLOWED_ORDER_BY = [
        'id', 'return_id', 'product_id', 'quantity', 'refund_amount', 'created_at'
    ];
    private const FILTERABLE_COLUMNS = [
        'return_id', 'product_id', 'order_item_id'
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
        // تم إزالة p.title و p.sku لتجنب الخطأ
        // الربط بجدول returns ضروري للتحقق من tenant_id
        $sql = "
            SELECT ri.*,
                   r.return_number
            FROM " . self::TABLE . " ri
            INNER JOIN returns r   ON r.id   = ri.return_id
            WHERE r.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND ri.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ri.{$orderBy} {$orderDir}";

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
            FROM " . self::TABLE . " ri
            INNER JOIN returns r ON r.id = ri.return_id
            WHERE r.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND ri.{$col} = :{$col}";
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
            SELECT ri.*,
                   r.return_number
            FROM " . self::TABLE . " ri
            INNER JOIN returns r   ON r.id   = ri.return_id
            WHERE r.tenant_id = :tenant_id AND ri.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        // التحقق من ملكية return_id للمستأجر قبل الحفظ
        if (!$this->validateReturnOwnership($tenantId, (int)$data['return_id'])) {
            throw new RuntimeException('Return request does not belong to tenant');
        }

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    return_id      = :return_id,
                    order_item_id  = :order_item_id,
                    product_id     = :product_id,
                    quantity       = :quantity,
                    reason         = :reason,
                    refund_amount  = :refund_amount
                WHERE id = :id
            ");
            $stmt->execute($this->buildParams($data, true));
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                return_id, order_item_id, product_id, quantity, reason, refund_amount
            ) VALUES (
                :return_id, :order_item_id, :product_id, :quantity, :reason, :refund_amount
            )
        ");
        $stmt->execute($this->buildParams($data, false));
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        // الحذف مع التأكد من الملكية عبر JOIN
        $stmt = $this->pdo->prepare("
            DELETE ri FROM " . self::TABLE . " ri
            INNER JOIN returns r ON r.id = ri.return_id
            WHERE ri.id = :id AND r.tenant_id = :tenant_id
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
            ':return_id'      => (int)$data['return_id'],
            ':order_item_id'  => (int)$data['order_item_id'],
            ':product_id'     => (int)$data['product_id'],
            ':quantity'       => (int)$data['quantity'],
            ':reason'         => $data['reason'] ?? null,
            ':refund_amount'  => $data['refund_amount'] ?? null,
        ];
        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];
        }
        return $params;
    }
}
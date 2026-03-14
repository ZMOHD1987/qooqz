<?php
declare(strict_types=1);

final class PdoEntityPaymentMethodsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id','payment_method_id','is_active','created_at'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List
    // ================================
    public function all(
        int $tenantId,
        int $entityId,
        ?int $limit = null,
        ?int $offset = null,
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        array $filters = []
    ): array {
        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $whereParts = [];
        $params = [];
        if ($tenantId > 0) {
            $whereParts[] = "e.tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }
        if ($entityId > 0) {
            $whereParts[] = "p.entity_id = :entity_id";
            $params[':entity_id'] = $entityId;
        }
        $where = count($whereParts) > 0 ? implode(' AND ', $whereParts) : '1=1';

        if (!empty($filters['search'])) {
            $where .= " AND (p.account_email LIKE :search OR p.account_id LIKE :search OR pm.method_name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['payment_method_id'])) {
            $where .= " AND p.payment_method_id = :pm_id";
            $params[':pm_id'] = (int)$filters['payment_method_id'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where .= " AND p.is_active = :is_active";
            $params[':is_active'] = (int)$filters['is_active'];
        }
        if (!empty($filters['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
            $where .= " AND p.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
            $where .= " AND p.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql = "
            SELECT p.*, pm.method_key, pm.method_name, pm.gateway_name, pm.icon_url, e.store_name AS entity_name, e.tenant_id AS row_tenant_id
            FROM entity_payment_methods p
            INNER JOIN entities e ON e.id = p.entity_id
            LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
            WHERE {$where}
            ORDER BY {$orderBy} {$orderDir}
        ";

        if ($limit !== null)  $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null)  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $decTenant = (int)($row['row_tenant_id'] ?? $tenantId);
            $decEntity = (int)($row['entity_id'] ?? $entityId);

            try {
                $row['account_email'] = $row['account_email']
                    ? Security::decryptForEntity($row['account_email'], $decTenant, $decEntity)
                    : null;
            } catch (Throwable $e) {
                $row['account_email'] = null;
            }

            try {
                $row['account_id'] = $row['account_id']
                    ? Security::decryptForEntity($row['account_id'], $decTenant, $decEntity)
                    : null;
            } catch (Throwable $e) {
                $row['account_id'] = null;
            }

            unset($row['row_tenant_id']);
        }

        return $rows;
    }

    // ================================
    // Find
    // ================================
    public function find(int $tenantId, int $entityId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, pm.method_key, pm.method_name, pm.gateway_name, pm.icon_url
            FROM entity_payment_methods p
            INNER JOIN entities e ON e.id = p.entity_id
            LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
            WHERE p.id = :id
              AND p.entity_id = :entity_id
              AND e.tenant_id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id'=>$id,
            ':entity_id'=>$entityId,
            ':tenant_id'=>$tenantId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        try {
            $row['account_email'] = $row['account_email']
                ? Security::decryptForEntity($row['account_email'], $tenantId, $entityId)
                : null;
        } catch (Throwable $e) {
            $row['account_email'] = null;
        }

        try {
            $row['account_id'] = $row['account_id']
                ? Security::decryptForEntity($row['account_id'], $tenantId, $entityId)
                : null;
        } catch (Throwable $e) {
            $row['account_id'] = null;
        }

        return $row;
    }

    // ================================
    // Save
    // ================================
    public function save(int $tenantId, int $entityId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        $encEmail = !empty($data['account_email'])
            ? Security::encryptForEntity($data['account_email'], $tenantId, $entityId)
            : null;

        $encAccountId = !empty($data['account_id'])
            ? Security::encryptForEntity($data['account_id'], $tenantId, $entityId)
            : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE entity_payment_methods SET
                    payment_method_id = :payment_method_id,
                    account_email = :email,
                    account_id = :account_id,
                    is_active = :is_active
                WHERE id = :id AND entity_id = :entity_id
            ");
            $stmt->execute([
                ':payment_method_id'=>(int)$data['payment_method_id'],
                ':email'=>$encEmail,
                ':account_id'=>$encAccountId,
                ':is_active'=>(int)($data['is_active'] ?? 1),
                ':id'=>$data['id'],
                ':entity_id'=>$entityId
            ]);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO entity_payment_methods (
                entity_id, payment_method_id, account_email, account_id, is_active
            ) VALUES (
                :entity_id, :payment_method_id, :email, :account_id, :is_active
            )
        ");
        $stmt->execute([
            ':entity_id'=>$entityId,
            ':payment_method_id'=>(int)$data['payment_method_id'],
            ':email'=>$encEmail,
            ':account_id'=>$encAccountId,
            ':is_active'=>(int)($data['is_active'] ?? 1)
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $tenantId, int $entityId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE p FROM entity_payment_methods p
            INNER JOIN entities e ON e.id = p.entity_id
            WHERE p.id = :id AND p.entity_id = :entity_id AND e.tenant_id = :tenant_id
        ");
        return $stmt->execute([
            ':id'=>$id,
            ':entity_id'=>$entityId,
            ':tenant_id'=>$tenantId
        ]);
    }
}
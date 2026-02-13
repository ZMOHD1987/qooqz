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
        string $orderDir = 'DESC'
    ): array {
        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "
            SELECT p.*, pm.method_key, pm.method_name, pm.gateway_name, pm.icon_url
            FROM entity_payment_methods p
            INNER JOIN entities e ON e.id = p.entity_id
            LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
            WHERE e.tenant_id = :tenant_id
              AND p.entity_id = :entity_id
            ORDER BY {$orderBy} {$orderDir}
        ";

        if ($limit !== null)  $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        if ($limit !== null)  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            try {
                $row['account_email'] = $row['account_email']
                    ? Security::decryptForEntity($row['account_email'], $tenantId, $entityId)
                    : null;
            } catch (Throwable $e) {
                $row['account_email'] = null; // Decryption failed
            }

            try {
                $row['account_id'] = $row['account_id']
                    ? Security::decryptForEntity($row['account_id'], $tenantId, $entityId)
                    : null;
            } catch (Throwable $e) {
                $row['account_id'] = null; // Decryption failed
            }
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

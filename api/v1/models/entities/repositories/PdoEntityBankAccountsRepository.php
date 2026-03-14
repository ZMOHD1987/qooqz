<?php
declare(strict_types=1);

final class PdoEntityBankAccountsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id','bank_name','is_primary','is_verified','created_at'
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

        $where = [];
        $params = [];
        if ($tenantId > 0) {
            $where[] = "e.tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }
        if ($entityId > 0) {
            $where[] = "b.entity_id = :entity_id";
            $params[':entity_id'] = $entityId;
        }

        if (!empty($filters['search'])) {
            $where[] = "(b.bank_name LIKE :search OR b.account_holder_name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = "b.is_verified = :is_active";
            $params[':is_active'] = (int) $filters['is_active'];
        }
        if (!empty($filters['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
            $where[] = "b.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
            $where[] = "b.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $allWhere = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT b.*, e.store_name AS entity_name, e.tenant_id AS row_tenant_id
            FROM entity_bank_accounts b
            INNER JOIN entities e ON e.id = b.entity_id
            {$allWhere}
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

        // فك التشفير
        // فك التشفير
        foreach ($rows as &$row) {
            $decTenant = (int)($row['row_tenant_id'] ?? $tenantId);
            $decEntity = (int)($row['entity_id'] ?? $entityId);

            try {
                $row['account_number'] = Security::decryptForEntity(
                    $row['account_number'], $decTenant, $decEntity
                );
            } catch (Throwable $e) {
                $row['account_number'] = null;
            }

            try {
                $row['iban'] = $row['iban']
                    ? Security::decryptForEntity($row['iban'], $decTenant, $decEntity)
                    : null;
            } catch (Throwable $e) {
                $row['iban'] = null;
            }

            try {
                $row['swift_code'] = $row['swift_code']
                    ? Security::decryptForEntity($row['swift_code'], $decTenant, $decEntity)
                    : null;
            } catch (Throwable $e) {
                $row['swift_code'] = null;
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
            SELECT b.*
            FROM entity_bank_accounts b
            INNER JOIN entities e ON e.id = b.entity_id
            WHERE b.id = :id
              AND b.entity_id = :entity_id
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
            $row['account_number'] = Security::decryptForEntity(
                $row['account_number'], $tenantId, $entityId
            );
        } catch (Throwable $e) {
            $row['account_number'] = null;
        }

        try {
            $row['iban'] = $row['iban']
                ? Security::decryptForEntity($row['iban'], $tenantId, $entityId)
                : null;
        } catch (Throwable $e) {
            $row['iban'] = null;
        }

        try {
            $row['swift_code'] = $row['swift_code']
                ? Security::decryptForEntity($row['swift_code'], $tenantId, $entityId)
                : null;
        } catch (Throwable $e) {
            $row['swift_code'] = null;
        }

        return $row;
    }

    // ================================
    // Save
    // ================================
    public function save(int $tenantId, int $entityId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        $encAccount = Security::encryptForEntity(
            $data['account_number'], $tenantId, $entityId
        );
        $encIban = !empty($data['iban'])
            ? Security::encryptForEntity($data['iban'], $tenantId, $entityId)
            : null;
        $encSwift = !empty($data['swift_code'])
            ? Security::encryptForEntity($data['swift_code'], $tenantId, $entityId)
            : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE entity_bank_accounts SET
                    bank_name = :bank_name,
                    account_holder_name = :holder,
                    account_number = :account_number,
                    iban = :iban,
                    swift_code = :swift,
                    is_primary = :is_primary,
                    is_verified = :is_verified
                WHERE id = :id AND entity_id = :entity_id
            ");
            $stmt->execute([
                ':bank_name'=>$data['bank_name'],
                ':holder'=>$data['account_holder_name'],
                ':account_number'=>$encAccount,
                ':iban'=>$encIban,
                ':swift'=>$encSwift,
                ':is_primary'=>(int)($data['is_primary'] ?? 0),
                ':is_verified'=>(int)($data['is_verified'] ?? 0),
                ':id'=>$data['id'],
                ':entity_id'=>$entityId
            ]);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO entity_bank_accounts (
                entity_id, bank_name, account_holder_name,
                account_number, iban, swift_code,
                is_primary, is_verified
            ) VALUES (
                :entity_id, :bank_name, :holder,
                :account_number, :iban, :swift,
                :is_primary, :is_verified
            )
        ");
        $stmt->execute([
            ':entity_id'=>$entityId,
            ':bank_name'=>$data['bank_name'],
            ':holder'=>$data['account_holder_name'],
            ':account_number'=>$encAccount,
            ':iban'=>$encIban,
            ':swift'=>$encSwift,
            ':is_primary'=>(int)($data['is_primary'] ?? 0),
            ':is_verified'=>0
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $tenantId, int $entityId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE b FROM entity_bank_accounts b
            INNER JOIN entities e ON e.id = b.entity_id
            WHERE b.id = :id AND b.entity_id = :entity_id AND e.tenant_id = :tenant_id
        ");
        return $stmt->execute([
            ':id'=>$id,
            ':entity_id'=>$entityId,
            ':tenant_id'=>$tenantId
        ]);
    }
}
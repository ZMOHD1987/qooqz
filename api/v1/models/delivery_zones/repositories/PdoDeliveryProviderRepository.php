<?php
declare(strict_types=1);

/**
 * PdoDeliveryProviderRepository
 *
 * PDO implementation of DeliveryProviderRepositoryInterface.
 *
 * Location: api/v1/models/delivery_zones/repositories/PdoDeliveryProviderRepository.php
 */
final class PdoDeliveryProviderRepository implements DeliveryProviderRepositoryInterface
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'dp.id', 'dp.rating', 'dp.total_deliveries', 'dp.created_at', 'dp.provider_type'
    ];

    private const FILTERABLE_COLUMNS = [
        'provider_type', 'vehicle_type', 'is_online', 'is_active', 'entity_id'
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
        string $orderBy = 'dp.id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        // Assuming a 'users' or 'tenant_users' table exists for names. 
        // Using LEFT JOIN for safety if table doesn't exist in schema provided.
        $sql = "
            SELECT dp.* 
            FROM delivery_providers dp
            WHERE dp.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];

        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'dp.id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
            $params[':offset'] = $offset;
        }

        return $this->fetchAll($sql, $params);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM delivery_providers dp WHERE dp.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $sql = "
            SELECT dp.*
            FROM delivery_providers dp
            WHERE dp.tenant_id = :tenant_id
              AND dp.id = :id
            LIMIT 1
        ";

        $params = [
            ':tenant_id' => $tenantId,
            ':id'        => $id,
        ];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO delivery_providers (
                tenant_id, tenant_user_id, entity_id, provider_type, vehicle_type, 
                license_number, is_online, is_active, rating, total_deliveries
            ) VALUES (
                :tenant_id, :tenant_user_id, :entity_id, :provider_type, :vehicle_type, 
                :license_number, :is_online, :is_active, :rating, :total_deliveries
            )
        ");

        $stmt->execute([
            ':tenant_id'        => $tenantId,
            ':tenant_user_id'   => $data['tenant_user_id'],
            ':entity_id'        => $data['entity_id'] ?? null,
            ':provider_type'    => $data['provider_type'],
            ':vehicle_type'     => $data['vehicle_type'] ?? 'bike',
            ':license_number'   => $data['license_number'] ?? null,
            ':is_online'        => $data['is_online'] ?? 0,
            ':is_active'        => $data['is_active'] ?? 1,
            ':rating'           => $data['rating'] ?? 0.00,
            ':total_deliveries' => $data['total_deliveries'] ?? 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $tenantId, array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('Provider ID required for update.');
        }

        $fields = [];
        $params = [':id' => $data['id'], ':tenant_id' => $tenantId];

        $allowedFields = [
            'tenant_user_id', 'entity_id', 'provider_type', 'vehicle_type', 
            'license_number', 'is_online', 'is_active', 'rating', 'total_deliveries'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return true;
        }

        $sql = 'UPDATE delivery_providers SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :tenant_id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM delivery_providers WHERE id = :id AND tenant_id = :tenant_id");
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function applyFilters(string $sql, array $params, array $filters): array
    {
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND dp.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }
        return [$sql, $params];
    }

    private function fetchAll(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
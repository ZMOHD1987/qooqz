<?php
declare(strict_types=1);

/**
 * PdoProviderZoneRepository
 *
 * PDO implementation of ProviderZoneRepositoryInterface.
 *
 * Location: api/v1/models/provider_zones/repositories/PdoProviderZoneRepository.php
 */
final class PdoProviderZoneRepository implements ProviderZoneRepositoryInterface
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'pz.provider_id', 'pz.zone_id', 'pz.assigned_at', 'pz.is_active'
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
        string $orderBy = 'pz.provider_id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        // Join providers and zones to ensure tenant scope and get names
        $sql = "
            SELECT pz.*, 
                   dp.provider_type, 
                   dz.zone_name
            FROM provider_zones pz
            INNER JOIN delivery_providers dp ON pz.provider_id = dp.id
            INNER JOIN delivery_zones dz ON pz.zone_id = dz.id
            WHERE dp.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];

        // Filters
        if (!empty($filters['provider_id'])) {
            $sql .= " AND pz.provider_id = :provider_id";
            $params[':provider_id'] = $filters['provider_id'];
        }
        if (!empty($filters['zone_id'])) {
            $sql .= " AND pz.zone_id = :zone_id";
            $params[':zone_id'] = $filters['zone_id'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND pz.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'pz.provider_id';
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

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM provider_zones pz
            INNER JOIN delivery_providers dp ON pz.provider_id = dp.id
            WHERE dp.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['provider_id'])) {
            $sql .= " AND pz.provider_id = :provider_id";
            $params[':provider_id'] = $filters['provider_id'];
        }
        if (!empty($filters['zone_id'])) {
            $sql .= " AND pz.zone_id = :zone_id";
            $params[':zone_id'] = $filters['zone_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $tenantId, int $providerId, int $zoneId, string $lang = 'ar'): ?array
    {
        $sql = "
            SELECT pz.*
            FROM provider_zones pz
            INNER JOIN delivery_providers dp ON pz.provider_id = dp.id
            WHERE dp.tenant_id = :tenant_id
              AND pz.provider_id = :provider_id
              AND pz.zone_id = :zone_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tenant_id'   => $tenantId,
            ':provider_id' => $providerId,
            ':zone_id'     => $zoneId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(int $tenantId, array $data): bool
    {
        // Validate existence of provider and zone in tenant scope implicitly via FK or explicit check
        // Here we rely on DB FK constraints or previous validation layers.
        
        $sql = "
            INSERT INTO provider_zones (provider_id, zone_id, is_active)
            VALUES (:provider_id, :zone_id, :is_active)
        ";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':provider_id' => $data['provider_id'],
            ':zone_id'     => $data['zone_id'],
            ':is_active'   => $data['is_active'] ?? 1
        ]);
    }

    public function update(int $tenantId, array $data): bool
    {
        if (empty($data['provider_id']) || empty($data['zone_id'])) {
            throw new InvalidArgumentException('provider_id and zone_id are required for update.');
        }

        // Check tenant scope
        $exists = $this->find($tenantId, (int)$data['provider_id'], (int)$data['zone_id']);
        if (!$exists) {
            return false; // Or throw Exception
        }

        $fields = [];
        $params = [
            ':provider_id' => $data['provider_id'],
            ':zone_id'     => $data['zone_id']
        ];

        if (isset($data['is_active'])) {
            $fields[] = 'is_active = :is_active';
            $params[':is_active'] = $data['is_active'];
        }

        if (empty($fields)) {
            return true;
        }

        $sql = 'UPDATE provider_zones SET ' . implode(', ', $fields) . ' 
                 WHERE provider_id = :provider_id AND zone_id = :zone_id';
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $tenantId, int $providerId, int $zoneId): bool
    {
        // Check tenant scope first
        $exists = $this->find($tenantId, $providerId, $zoneId);
        if (!$exists) {
            return false;
        }

        $sql = "DELETE FROM provider_zones 
                 WHERE provider_id = :provider_id AND zone_id = :zone_id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':provider_id' => $providerId,
            ':zone_id'     => $zoneId
        ]);
    }
}
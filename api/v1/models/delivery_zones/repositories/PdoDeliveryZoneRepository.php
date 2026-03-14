<?php
declare(strict_types=1);

/**
 * PdoDeliveryZoneRepository
 *
 * PDO implementation of DeliveryZoneRepositoryInterface.
 *
 * Location: api/v1/models/delivery_zones/repositories/PdoDeliveryZoneRepository.php
 */
final class PdoDeliveryZoneRepository implements DeliveryZoneRepositoryInterface
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'dz.id', 'dz.zone_name', 'dz.zone_type', 'dz.delivery_fee', 'dz.created_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'provider_id', 'zone_type', 'city_id', 'is_active'
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
        string $orderBy = 'dz.id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $sql = "
            SELECT dz.*, c.name AS city_name
            FROM delivery_zones dz
            LEFT JOIN cities c ON dz.city_id = c.id
            WHERE dz.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];

        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'dz.id';
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
        $sql = "SELECT COUNT(*) FROM delivery_zones dz WHERE dz.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $sql = "
            SELECT dz.*, c.name AS city_name
            FROM delivery_zones dz
            LEFT JOIN cities c ON dz.city_id = c.id
            WHERE dz.tenant_id = :tenant_id
              AND dz.id = :id
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
            INSERT INTO delivery_zones (
                tenant_id, provider_id, zone_name, zone_type, city_id, 
                center_lat, center_lng, radius_km, zone_value, delivery_fee, 
                free_delivery_over, min_order_value, estimated_minutes, is_active
            ) VALUES (
                :tenant_id, :provider_id, :zone_name, :zone_type, :city_id, 
                :center_lat, :center_lng, :radius_km, :zone_value, :delivery_fee, 
                :free_delivery_over, :min_order_value, :estimated_minutes, :is_active
            )
        ");

        $stmt->execute([
            ':tenant_id'          => $tenantId,
            ':provider_id'        => $data['provider_id'],
            ':zone_name'          => $data['zone_name'],
            ':zone_type'          => $data['zone_type'],
            ':city_id'            => $data['city_id'] ?? null,
            ':center_lat'         => $data['center_lat'] ?? null,
            ':center_lng'         => $data['center_lng'] ?? null,
            ':radius_km'          => $data['radius_km'] ?? null,
            ':zone_value'         => isset($data['zone_value'])
                                        ? (is_array($data['zone_value']) ? json_encode($data['zone_value'], JSON_UNESCAPED_UNICODE) : $data['zone_value'])
                                        : null,
            ':delivery_fee'       => $data['delivery_fee'],
            ':free_delivery_over' => $data['free_delivery_over'] ?? null,
            ':min_order_value'    => $data['min_order_value'] ?? null,
            ':estimated_minutes'  => $data['estimated_minutes'] ?? 45,
            ':is_active'          => $data['is_active'] ?? 1,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $tenantId, array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('Zone ID required for update.');
        }

        $fields = [];
        $params = [':id' => $data['id'], ':tenant_id' => $tenantId];

        $allowedFields = [
            'provider_id', 'zone_name', 'zone_type', 'city_id', 
            'center_lat', 'center_lng', 'radius_km', 'zone_value', 'delivery_fee', 
            'free_delivery_over', 'min_order_value', 'estimated_minutes', 'is_active'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $value = $data[$field];
                if ($field === 'zone_value' && is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                $params[":{$field}"] = $value;
            }
        }

        if (empty($fields)) {
            return true;
        }

        $sql = 'UPDATE delivery_zones SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :tenant_id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM delivery_zones WHERE id = :id AND tenant_id = :tenant_id");
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function applyFilters(string $sql, array $params, array $filters): array
    {
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND dz.{$col} = :{$col}";
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
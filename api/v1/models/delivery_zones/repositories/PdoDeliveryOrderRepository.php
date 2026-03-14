<?php
declare(strict_types=1);

/**
 * PdoDeliveryOrderRepository
 *
 * PDO implementation of DeliveryOrderRepositoryInterface.
 *
 * Location: api/v1/models/delivery_orders/repositories/PdoDeliveryOrderRepository.php
 */
final class PdoDeliveryOrderRepository implements DeliveryOrderRepositoryInterface
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'dord.id', 'dord.order_id', 'dord.delivery_status', 'dord.created_at', 'dord.delivery_fee'
    ];

    private const FILTERABLE_COLUMNS = [
        'order_id', 'provider_id', 'delivery_status', 'delivery_zone_id'
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
        string $orderBy = 'dord.id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $sql = "
            SELECT dord.* 
            FROM delivery_orders dord
            WHERE dord.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];

        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'dord.id';
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
        $sql = "SELECT COUNT(*) FROM delivery_orders dord WHERE dord.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $sql = "
            SELECT dord.*
            FROM delivery_orders dord
            WHERE dord.tenant_id = :tenant_id
              AND dord.id = :id
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
            INSERT INTO delivery_orders (
                tenant_id, order_id, provider_id, pickup_address_id, dropoff_address_id,
                delivery_zone_id, delivery_status, delivery_fee, calculated_fee, provider_payout
            ) VALUES (
                :tenant_id, :order_id, :provider_id, :pickup_address_id, :dropoff_address_id,
                :delivery_zone_id, :delivery_status, :delivery_fee, :calculated_fee, :provider_payout
            )
        ");

        $stmt->execute([
            ':tenant_id'          => $tenantId,
            ':order_id'           => $data['order_id'],
            ':provider_id'        => isset($data['provider_id']) && $data['provider_id'] !== '' ? (int)$data['provider_id'] : null,
            ':pickup_address_id'  => $data['pickup_address_id'] ?? null,
            ':dropoff_address_id' => $data['dropoff_address_id'] ?? null,
            ':delivery_zone_id'   => isset($data['delivery_zone_id']) && $data['delivery_zone_id'] !== '' ? (int)$data['delivery_zone_id'] : null,
            ':delivery_status'    => $data['delivery_status'] ?? 'pending',
            ':delivery_fee'       => $data['delivery_fee'] ?? 0.00,
            ':calculated_fee'     => $data['calculated_fee'] ?? 0.00,
            ':provider_payout'    => $data['provider_payout'] ?? 0.00,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $tenantId, array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('Order ID required for update.');
        }

        $fields = [];
        $params = [':id' => $data['id'], ':tenant_id' => $tenantId];

        $allowedFields = [
            'provider_id', 'delivery_status', 'pickup_address_id', 'dropoff_address_id',
            'delivery_zone_id', 'delivery_fee', 'calculated_fee', 'provider_payout', 
            'cancelled_by', 'cancellation_reason', 'rejection_count'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        // Handle status timestamps automatically
        if (isset($data['delivery_status'])) {
            if ($data['delivery_status'] === 'assigned' && empty($data['assigned_at'])) {
                $fields[] = 'assigned_at = NOW()';
            }
            if ($data['delivery_status'] === 'picked_up' && empty($data['picked_up_at'])) {
                $fields[] = 'picked_up_at = NOW()';
            }
            if ($data['delivery_status'] === 'delivered' && empty($data['delivered_at'])) {
                $fields[] = 'delivered_at = NOW()';
            }
        }

        if (empty($fields)) {
            return true;
        }

        $sql = 'UPDATE delivery_orders SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :tenant_id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM delivery_orders WHERE id = :id AND tenant_id = :tenant_id");
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function applyFilters(string $sql, array $params, array $filters): array
    {
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND dord.{$col} = :{$col}";
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